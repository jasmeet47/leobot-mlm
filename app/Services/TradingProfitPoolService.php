<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * Phase 1: one company-wide, manually approved trading-profit funding event.
 *
 * - Admin confirms an independently verified profit statement reference.
 * - The designated company_wallet reserve MUST ALREADY contain available funds.
 * - One atomic operation debits company_wallet, credits generation_pool_accounts,
 *   writes matching financial_ledger entries and snapshots 51 level budgets.
 * - All newly funded money remains UNALLOCATED. No member claims/payouts,
 *   no held claims, and no user balances change in this phase.
 *
 * IMPORTANT: Company wallet balance by itself does not prove profit origin.
 * The operator must verify that the funds are free of member-principal and
 * other liabilities before the first funding operation is authorized.
 */
final class TradingProfitPoolService
{
    private const SCALE = 8;
    private const RATE_SCALE = 9;
    private const MAX_BALANCE = '999999999999.99999999';

    /**
     * Fund the pool exactly once for a UNIQUE profit statement reference.
     * Idempotent retry returns the original funding ID for the same amount;
     * reusing a reference for a different amount is rejected.
     *
     * NEVER call this from activation or a scheduler. This is a deliberate,
     * separately authorized admin accounting operation only.
     */
    public static function fund(
        User $admin,
        string $verifiedProfitAmount,
        string $profitReference
    ): int {
        self::assertAdmin($admin);
        self::assertMoney($verifiedProfitAmount);
        self::assertReference($profitReference);
        $profitReference = strtoupper($profitReference);

        return DB::transaction(function () use (
            $admin,
            $verifiedProfitAmount,
            $profitReference
        ): int {
            // Lock shared funds first: serializes concurrent funding requests.
            $company = DB::table('company_wallet')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (!$company) {
                throw new RuntimeException('Company wallet is missing.');
            }

            // Block legacy 30%-only funding when unified 67/30/3 mode is active.
            // Old tests and previously funded records remain unaffected while OFF.
            if (Schema::hasTable('profit_sharing_settings')) {
                $unified = DB::table('profit_sharing_settings')->where('id', 1)
                    ->lockForUpdate()->first();
                if ($unified && self::asBoolean($unified->combined_funding_enabled)) {
                    throw new RuntimeException(
                        'Use unified profit sharing funding; legacy generation-only funding is disabled.'
                    );
                }
            }

            $pool = DB::table('generation_pool_accounts')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (!$pool) {
                throw new RuntimeException('Generation pool account is missing.');
            }

            // Idempotency is based on the real profit source, not a new UUID
            // created for every retry. Unique DB constraint is the fallback.
            $existing = DB::table('generation_profit_fundings')
                ->where('profit_reference', $profitReference)
                ->first();

            if ($existing) {
                if (bccomp(
                    (string) $existing->verified_profit_amount,
                    $verifiedProfitAmount,
                    self::SCALE
                ) !== 0) {
                    throw new RuntimeException(
                        'Profit reference already funded for another amount.'
                    );
                }

                return (int) $existing->id;
            }

            $settings = DB::table('generation_pool_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (!$settings) {
                throw new RuntimeException('Generation pool settings are missing.');
            }

            // Funding is staged-only; leave distribution disabled in phase 1.
            if (self::asBoolean($settings->generation_enabled)) {
                throw new RuntimeException(
                    'Disable generation distribution before phase-1 funding.'
                );
            }

            $percent = (string) $settings->trading_profit_pool_percent;
            self::assertPercentage($percent);

            // Verify Generation + Magic budget cannot allocate >100% of
            // the same trading profit, even if settings bypassed the UI.
            $magic = DB::table('magic_income_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();
            if (!$magic) {
                throw new RuntimeException('Magic income settings are missing.');
            }
            $magicPercent = (string) $magic->trading_profit_pool_percent;
            self::assertPercentage($magicPercent);
            if (bccomp(bcadd($percent, $magicPercent, self::RATE_SCALE), '100', self::RATE_SCALE) > 0) {
                throw new RuntimeException('Generation + Magic pool percentages exceed 100%.');
            }

            // Funding does not enable generation_enabled or trigger payout.
            $fundingAmount = self::percentOf($verifiedProfitAmount, $percent);
            if (bccomp($fundingAmount, '0', self::SCALE) <= 0) {
                throw new RuntimeException('Calculated pool amount must be positive.');
            }

            $beforeCompany = (string) $company->balance;
            $beforePool = (string) $pool->balance;
            $beforeUnallocated = (string) $pool->unallocated_balance;
            $beforeTotalFunded = (string) $pool->total_funded;
            self::assertStoredBalance($beforeCompany);
            self::assertStoredBalance($beforePool);
            self::assertStoredBalance($beforeUnallocated);
            self::assertStoredBalance($beforeTotalFunded);
            self::assertStoredBalance((string) $pool->held_balance);
            self::assertStoredBalance((string) $pool->payable_balance);
            self::assertStoredBalance((string) $pool->total_paid);

            // Pool may already contain Phase-2 held/payable classifications.
            // No money has been paid in this phase: all balances reconcile.
            $reconciled = bcadd(
                bcadd($beforeUnallocated, (string) $pool->held_balance, self::SCALE),
                (string) $pool->payable_balance,
                self::SCALE
            );
            $fundedLessPaid = bcsub(
                $beforeTotalFunded,
                (string) $pool->total_paid,
                self::SCALE
            );
            if (
                bccomp($beforePool, $reconciled, self::SCALE) !== 0 ||
                bccomp($beforePool, $fundedLessPaid, self::SCALE) !== 0 ||
                bccomp((string) $pool->total_paid, '0', self::SCALE) !== 0
            ) {
                throw new RuntimeException('Generation reserve accounting is inconsistent.');
            }

            if (bccomp($beforeCompany, $fundingAmount, self::SCALE) < 0) {
                throw new RuntimeException('Insufficient available company wallet funds.');
            }

            $afterCompany = bcsub($beforeCompany, $fundingAmount, self::SCALE);
            $afterPool = bcadd($beforePool, $fundingAmount, self::SCALE);
            self::assertStoredBalance($afterPool);

            $rates = DB::table('level_settings')
                ->orderBy('level_number')
                ->lockForUpdate()
                ->get(['level_number', 'commission_percentage', 'is_active']);

            if ($rates->count() !== 51) {
                throw new RuntimeException('Exactly 51 level settings are required.');
            }

            $levelRows = [];
            $rateTotal = '0.000000000';
            $allocatedTotal = '0.00000000';

            foreach ($rates as $index => $row) {
                $level = (int) $row->level_number;
                if ($level !== $index + 1) {
                    throw new RuntimeException('Level numbers must be 1 through 51.');
                }

                $rate = (string) $row->commission_percentage;
                self::assertPercentage($rate);
                $rateTotal = bcadd($rateTotal, $rate, self::RATE_SCALE);
                $share = self::percentOf($fundingAmount, $rate);
                $allocatedTotal = bcadd($allocatedTotal, $share, self::SCALE);

                $levelRows[] = [
                    'level_number' => $level,
                    'rate_percent_snapshot' => $rate,
                    'was_active' => self::asBoolean($row->is_active),
                    'budget_amount' => $share,
                    'unallocated_amount' => $share,
                    'held_amount' => '0.00000000',
                    'payable_amount' => '0.00000000',
                    'paid_amount' => '0.00000000',
                ];
            }

            if (bccomp($rateTotal, '100.000000000', self::RATE_SCALE) !== 0) {
                throw new RuntimeException('Level allocation rates must total 100%.');
            }

            $dust = bcsub($fundingAmount, $allocatedTotal, self::SCALE);
            if (bccomp($dust, '0', self::SCALE) < 0) {
                throw new RuntimeException('Level allocations exceed funded pool.');
            }

            // One row per verified profit statement/period. Nothing distributed.
            $fundingId = DB::table('generation_profit_fundings')->insertGetId([
                'profit_reference' => $profitReference,
                'approved_by' => $admin->getKey(),
                'verified_profit_amount' => $verifiedProfitAmount,
                'pool_percentage_snapshot' => $percent,
                'pool_amount' => $fundingAmount,
                'rounding_unallocated' => $dust,
                'status' => 'funded_unallocated',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($levelRows as $levelRow) {
                DB::table('generation_level_budgets')->insert(array_merge(
                    $levelRow,
                    [
                        'funding_id' => $fundingId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ));
            }

            DB::table('company_wallet')->where('id', 1)->update([
                'balance' => $afterCompany,
                'updated_at' => now(),
            ]);

            DB::table('generation_pool_accounts')->where('id', 1)->update([
                'balance' => $afterPool,
                'unallocated_balance' => bcadd(
                    $beforeUnallocated,
                    $fundingAmount,
                    self::SCALE
                ),
                'total_funded' => bcadd(
                    $beforeTotalFunded,
                    $fundingAmount,
                    self::SCALE
                ),
                'updated_at' => now(),
            ]);

            // Two independent, unique ledger keys. Signed journal entries
            // reconcile to zero for this internal transfer.
            self::insertLedger(
                $fundingId,
                'generation_pool_company_out',
                'company_wallet',
                '-' . $fundingAmount,
                $beforeCompany,
                $afterCompany,
                'generation:company_out:' . hash('sha256', $profitReference)
            );

            self::insertLedger(
                $fundingId,
                'generation_pool_reserve_in',
                'generation_pool',
                $fundingAmount,
                $beforePool,
                $afterPool,
                'generation:pool_in:' . hash('sha256', $profitReference)
            );

            return (int) $fundingId;
        }, 3);
    }

    private static function insertLedger(
        int $fundingId,
        string $entryType,
        string $walletType,
        string $signedAmount,
        string $before,
        string $after,
        string $transactionKey
    ): void {
        DB::table('financial_ledger')->insert([
            'user_id' => null,
            'entry_type' => $entryType,
            'wallet_type' => $walletType,
            'amount' => $signedAmount,
            'balance_before' => $before,
            'balance_after' => $after,
            'from_user_id' => null,
            'reference_user_id' => null,
            'level' => null,
            'reference_type' => 'generation_profit_funding',
            'reference_id' => $fundingId,
            'transaction_key' => $transactionKey,
            'status' => 'completed',
            'description' => 'Internal transfer to the unfunded-recipient generation reserve.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function assertAdmin(User $admin): void
    {
        if (
            !$admin->exists ||
            strtoupper((string) $admin->username) !== 'ADMIN' ||
            $admin->status !== 'active'
        ) {
            throw new AuthorizationException('Active ADMIN authorization required.');
        }
    }

    private static function assertReference(string $reference): void
    {
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{2,99}\z/D', $reference)) {
            throw new InvalidArgumentException(
                'Profit reference must be 3-100 safe characters.'
            );
        }
    }

    private static function assertMoney(string $amount): void
    {
        if (
            !preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) ||
            bccomp($amount, '0', self::SCALE) <= 0 ||
            bccomp($amount, self::MAX_BALANCE, self::SCALE) > 0
        ) {
            throw new InvalidArgumentException('Invalid positive profit amount.');
        }
    }

    private static function assertStoredBalance(string $balance): void
    {
        if (
            !preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $balance) ||
            bccomp($balance, '0', self::SCALE) < 0 ||
            bccomp($balance, self::MAX_BALANCE, self::SCALE) > 0
        ) {
            throw new RuntimeException('Invalid wallet/reserve balance.');
        }
    }

    private static function assertPercentage(string $rate): void
    {
        if (
            !preg_match('/\A\d+(?:\.\d{1,9})?\z/D', $rate) ||
            bccomp($rate, '0', self::RATE_SCALE) < 0 ||
            bccomp($rate, '100', self::RATE_SCALE) > 0
        ) {
            throw new RuntimeException('Invalid pool/level percentage.');
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
