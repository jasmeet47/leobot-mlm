<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Read-only calculations for LeoBot's two independently funded 51-level pools.
 *
 * Nothing here credits wallets, reserves funds, creates ledger entries,
 * promises a commission or distributes income.
 */
class CommissionService
{
    private const SCALE = 8;
    private const RATE_SCALE = 9;
    private const MAX_AMOUNT = '999999999999.99999999';
    private const MAX_LEVEL = 51;

    /** Preview the investment distribution budget and its 51 level shares. */
    public static function previewInvestment(string $investmentAmount): array
    {
        return self::preview('investment', $investmentAmount);
    }

    /** Preview the generation budget from an amount of trading profit. */
    public static function previewTradingProfit(string $tradingProfitAmount): array
    {
        return self::preview('trading_profit', $tradingProfitAmount);
    }

    /**
     * Preview the upline/qualification outcome for ONE member's investment.
     * The source member is excluded; level 1 is their direct sponsor.
     */
    public static function previewInvestmentForMember(
        int $sourceMemberId,
        string $investmentAmount
    ): array {
        return self::previewForMember('investment', $sourceMemberId, $investmentAmount);
    }

    /**
     * Preview a specific source member's upline for a trading-profit budget.
     * IMPORTANT: This is NOT a company-wide trading-profit distribution.
     * Do not call once per member using the SAME global profit amount: that
     * would multiply the pool. A global allocation rule must be designed first.
     */
    public static function previewTradingProfitForMember(
        int $sourceMemberId,
        string $tradingProfitAmount
    ): array {
        return self::previewForMember('trading_profit', $sourceMemberId, $tradingProfitAmount);
    }

    /**
     * Return a read-only qualification / held / unallocated estimate.
     *
     * Assumptions FOR PREVIEW ONLY:
     * - A sponsor at generation N is considered for level N.
     * - A recipient needs active user status, >=1 active activation package,
     *   and that level unlocked under BusinessQualificationService.
     * - A known but unqualified sponsor's amount is labelled "held".
     * - Missing sponsors, disabled levels, levels above the configured max,
     *   and rounding dust are labelled "unallocated".
     * - "held" means calculation-only; no funds are actually reserved.
     */
    private static function previewForMember(
        string $sourceType,
        int $sourceMemberId,
        string $sourceAmount
    ): array {
        if ($sourceMemberId <= 0) {
            throw new InvalidArgumentException('Invalid source member ID.');
        }

        $base = self::preview($sourceType, $sourceAmount);

        $source = DB::table('users')
            ->where('id', $sourceMemberId)
            ->first(['id', 'sponsor_user_id']);

        if (!$source) {
            throw new InvalidArgumentException('Source member does not exist.');
        }

        // Walk the source member's ancestors, detecting corrupted referral cycles.
        $uplines = [];
        $visited = [$sourceMemberId => true];
        $nextId = $source->sponsor_user_id === null
            ? null
            : (int) $source->sponsor_user_id;

        for ($level = 1; $level <= self::MAX_LEVEL && $nextId !== null; $level++) {
            if ($nextId <= 0 || isset($visited[$nextId])) {
                throw new RuntimeException('Invalid or cyclic sponsor relationship.');
            }

            $ancestor = DB::table('users')
                ->where('id', $nextId)
                ->first(['id', 'status', 'sponsor_user_id']);

            if (!$ancestor) {
                // Missing sponsor: remaining generations have no identifiable payee.
                break;
            }

            $visited[$nextId] = true;
            $uplines[$level] = $ancestor;
            $nextId = $ancestor->sponsor_user_id === null
                ? null
                : (int) $ancestor->sponsor_user_id;
        }

        $qualifiedTotal = '0.00000000';
        $heldTotal = '0.00000000';
        $unallocatedTotal = '0.00000000';
        $totalLevelShares = '0.00000000';
        $results = [];

        foreach ($base['levels'] as $row) {
            $level = $row['level'];
            $share = self::percentOf($base['pool_amount'], $row['rate_percent']);
            $totalLevelShares = bcadd($totalLevelShares, $share, self::SCALE);
            $recipient = $uplines[$level] ?? null;
            $recipientId = $recipient ? (int) $recipient->id : null;

            if (!$row['is_active'] || !$row['within_max_level']) {
                $status = 'unallocated_level_disabled_or_outside_limit';
                $bucket = 'unallocated';
            } elseif ($recipient === null) {
                $status = 'unallocated_missing_upline';
                $bucket = 'unallocated';
            } elseif ((string) $recipient->status !== 'active') {
                $status = 'held_recipient_inactive';
                $bucket = 'held';
            } elseif (!DB::table('activation_packages')
                ->where('user_id', $recipientId)
                ->where('status', 'active')
                ->exists()) {
                $status = 'held_no_active_package';
                $bucket = 'held';
            } elseif (!BusinessQualificationService::isLevelUnlocked($recipientId, $level)) {
                $status = 'held_business_not_qualified';
                $bucket = 'held';
            } else {
                $status = 'qualified_preview_only';
                $bucket = 'qualified';
            }

            if ($bucket === 'qualified') {
                $qualifiedTotal = bcadd($qualifiedTotal, $share, self::SCALE);
            } elseif ($bucket === 'held') {
                $heldTotal = bcadd($heldTotal, $share, self::SCALE);
            } else {
                $unallocatedTotal = bcadd($unallocatedTotal, $share, self::SCALE);
            }

            $results[] = array_merge($row, [
                'recipient_user_id' => $recipientId,
                'level_share' => $share,
                'qualification_status' => $status,
                'preview_bucket' => $bucket,
            ]);
        }

        // Each share is truncated to 8 decimals. Dust remains unallocated.
        $roundingRemainder = bcsub(
            $base['pool_amount'],
            $totalLevelShares,
            self::SCALE
        );

        if (bccomp($roundingRemainder, '0', self::SCALE) < 0) {
            throw new RuntimeException('Level shares exceed the pool.');
        }

        $unallocatedTotal = bcadd($unallocatedTotal, $roundingRemainder, self::SCALE);
        $reconciledTotal = bcadd(
            bcadd($qualifiedTotal, $heldTotal, self::SCALE),
            $unallocatedTotal,
            self::SCALE
        );

        if (bccomp($reconciledTotal, $base['pool_amount'], self::SCALE) !== 0) {
            throw new RuntimeException('Preview pool accounting did not reconcile.');
        }

        return array_merge($base, [
            'source_member_id' => $sourceMemberId,
            'qualified_preview_total' => $qualifiedTotal,
            'held_preview_total' => $heldTotal,
            'unallocated_preview_total' => $unallocatedTotal,
            'rounding_remainder' => $roundingRemainder,
            'levels' => $results,
            'funds_reserved' => false,
            'wallets_credited' => false,
            'is_preview_only' => true,
        ]);
    }

    private static function preview(string $sourceType, string $sourceAmount): array
    {
        self::validateAmount($sourceAmount);

        if ($sourceType === 'investment') {
            $settingsTable = 'investment_distribution_settings';
            $ratesTable = 'investment_distribution_level_rates';
            $percentField = 'distribution_percent';
            $enabledField = 'is_enabled';
        } elseif ($sourceType === 'trading_profit') {
            $settingsTable = 'generation_pool_settings';
            $ratesTable = 'level_settings';
            $percentField = 'trading_profit_pool_percent';
            $enabledField = 'generation_enabled';
        } else {
            throw new InvalidArgumentException('Invalid distribution type.');
        }

        $settings = DB::table($settingsTable)->where('id', 1)->first();

        if (!$settings) {
            throw new RuntimeException("Missing {$settingsTable} settings.");
        }

        $poolPercent = (string) $settings->{$percentField};
        self::validatePercent($poolPercent);

        $maxLevel = $sourceType === 'investment'
            ? (int) $settings->max_distribution_level
            : self::MAX_LEVEL;

        if ($maxLevel < 1 || $maxLevel > self::MAX_LEVEL) {
            throw new RuntimeException('Invalid maximum distribution level.');
        }

        $poolAmount = self::percentOf($sourceAmount, $poolPercent);

        $rates = DB::table($ratesTable)
            ->orderBy('level_number')
            ->get(['level_number', 'commission_percentage', 'is_active']);

        if ($rates->count() !== self::MAX_LEVEL) {
            throw new RuntimeException("Expected 51 rows in {$ratesTable}.");
        }

        $rateTotal = '0.000000000';
        $potentialTotal = '0.00000000';
        $levels = [];

        foreach ($rates as $index => $row) {
            $level = (int) $row->level_number;

            if ($level !== $index + 1) {
                throw new RuntimeException("Invalid level order in {$ratesTable}.");
            }

            $rate = (string) $row->commission_percentage;
            self::validatePercent($rate);
            $rateTotal = bcadd($rateTotal, $rate, self::RATE_SCALE);

            $isActive = self::asBoolean($row->is_active);
            $withinLimit = $level <= $maxLevel;
            $potentialAmount = ($isActive && $withinLimit)
                ? self::percentOf($poolAmount, $rate)
                : '0.00000000';

            $potentialTotal = bcadd($potentialTotal, $potentialAmount, self::SCALE);

            $levels[] = [
                'level' => $level,
                'rate_percent' => $rate,
                'is_active' => $isActive,
                'within_max_level' => $withinLimit,
                'potential_amount' => $potentialAmount,
            ];
        }

        if (bccomp($rateTotal, '100.000000000', self::RATE_SCALE) !== 0) {
            throw new RuntimeException(
                "{$ratesTable} percentages must total exactly 100%."
            );
        }

        if (bccomp($potentialTotal, $poolAmount, self::SCALE) > 0) {
            throw new RuntimeException('Calculated allocations exceed the pool.');
        }

        return [
            'source_type' => $sourceType,
            'source_amount' => $sourceAmount,
            'distribution_enabled' => self::asBoolean($settings->{$enabledField}),
            'pool_percent' => $poolPercent,
            'pool_amount' => $poolAmount,
            'rate_total_percent' => $rateTotal,
            'max_distribution_level' => $maxLevel,
            'potential_total' => $potentialTotal,
            'undistributed_preview_amount' => bcsub(
                $poolAmount,
                $potentialTotal,
                self::SCALE
            ),
            'levels' => $levels,
            'is_preview_only' => true,
        ];
    }

    private static function validateAmount(string $amount): void
    {
        if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/', $amount)
            || bccomp($amount, '0', self::SCALE) <= 0
            || bccomp($amount, self::MAX_AMOUNT, self::SCALE) > 0
        ) {
            throw new InvalidArgumentException('Invalid positive USDT amount.');
        }
    }

    private static function validatePercent(string $percent): void
    {
        if (!preg_match('/\A\d+(?:\.\d{1,9})?\z/', $percent)
            || bccomp($percent, '0', self::RATE_SCALE) < 0
            || bccomp($percent, '100', self::RATE_SCALE) > 0
        ) {
            throw new RuntimeException('Percentage must be between 0 and 100.');
        }
    }

    private static function percentOf(string $amount, string $percent): string
    {
        return bcdiv(
            bcmul($amount, $percent, self::SCALE + self::RATE_SCALE),
            '100',
            self::SCALE
        );
    }

    private static function asBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            strtolower((string) $value),
            ['1', 't', 'true', 'yes', 'on'],
            true
        );
    }
}
