<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * STAGED ONLY: reserve Magic Pool funds, never pay members.
 *
 * No controller, route, scheduler, or payout is supplied.
 * Disabled unless an explicit config gate is enabled after security review.
 * Underlying source-of-funds verification remains an operator responsibility.
 *
 * IMPORTANT: Before enabling, legacy Generation and Unified services must also
 * reject source references already present in magic_pool_fundings.
 */
final class MagicPoolFundingService
{
    private const SCALE = 8;
    private const MAX_MONEY = '999999999999.99999999';

    /**
     * Reserve the configured Magic percentage of an independently verified
     * trading-profit statement. The full verified profit must be available
     * and unencumbered in the company wallet (operator assertion).
     */
    public static function fundFromTradingProfit(
        User $admin,
        string $verifiedProfitAmount,
        string $sourceReference
    ): int {
        self::validateMoney($verifiedProfitAmount);

        return self::fund($admin, 'trading_profit', $verifiedProfitAmount, $sourceReference);
    }

    /**
     * Reserve an independently verified direct Magic Pool allocation.
     * Do NOT use this to fund a second slice of an already-funded profit
     * statement. Its source reference must identify distinct economic funds.
     */
    public static function fundDirectPool(
        User $admin,
        string $poolAmount,
        string $sourceReference
    ): int {
        self::validateMoney($poolAmount);

        return self::fund($admin, 'direct_pool', $poolAmount, $sourceReference);
    }

    private static function fund(
        User $admin,
        string $mode,
        string $inputAmount,
        string $sourceReference
    ): int {
        if (!config('leobot.magic_pool_funding_enabled', false)) {
            throw new RuntimeException(
                'Magic Pool funding is disabled pending security and accounting approval.'
            );
        }

        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{2,99}\z/D', $sourceReference)) {
            throw new InvalidArgumentException('Invalid source reference.');
        }

        $reference = strtoupper($sourceReference);

        if (!$admin->exists || (int) $admin->getKey() <= 0) {
            throw new AuthorizationException('Valid active ADMIN required.');
        }

        return DB::transaction(function () use ($admin, $mode, $inputAmount, $reference): int {
            $actualAdmin = DB::table('users')
                ->where('id', $admin->getKey())
                ->lockForUpdate()
                ->first(['id', 'role', 'status', 'is_blocked']);

            if (!$actualAdmin ||
                !in_array((string) $actualAdmin->role, ['admin', 'super_admin'], true) ||
                ((string) $actualAdmin->status !== 'active' || in_array($actualAdmin->is_blocked, [true, 1, '1', 't', 'true'], true))) {
                throw new AuthorizationException('Active ADMIN approval required.');
            }

            // Consistent lock order with UnifiedProfitSharingService.
            $company = DB::table('company_wallet')
                ->where('id', 1)->lockForUpdate()->first();
            $generationAccount = DB::table('generation_pool_accounts')
                ->where('id', 1)->lockForUpdate()->first();
            $reserves = DB::table('profit_sharing_reserve_accounts')
                ->where('id', 1)->lockForUpdate()->first();

            if (!$company || !$generationAccount || !$reserves) {
                throw new RuntimeException('Required reserve accounts are missing.');
            }

            $companyBalance = self::storedMoney($company->balance);
            $magicBalance = self::storedMoney($reserves->magic_balance);
            $totalMagicFunded = self::storedMoney($reserves->total_magic_funded);
            $selfBalance = self::storedMoney($reserves->self_balance);
            $totalSelfFunded = self::storedMoney($reserves->total_self_funded);

            foreach ([
                $companyBalance, $magicBalance, $totalMagicFunded,
                $selfBalance, $totalSelfFunded,
            ] as $amount) {
                self::validateStoredBalance($amount);
            }

            if (bccomp($magicBalance, $totalMagicFunded, self::SCALE) !== 0 ||
                bccomp($selfBalance, $totalSelfFunded, self::SCALE) !== 0) {
                throw new RuntimeException('SELF/Magic reserve accounting is inconsistent.');
            }

            // Check other funding systems before returning an idempotent retry.
            // These checks do not by themselves protect against DIFFERENT
            // references identifying the same underlying economic funds.
            if (DB::table('generation_profit_fundings')
                ->where('profit_reference', $reference)->exists() ||
                DB::table('profit_sharing_fundings')
                    ->where('profit_reference', $reference)->exists()) {
                throw new RuntimeException('Source reference already used by another funding system.');
            }

            $prior = DB::table('magic_pool_fundings')
                ->where('source_reference', $reference)->first();

            if ($prior) {
                if ((string) $prior->funding_mode !== $mode ||
                    (string) $prior->status !== 'reserved_no_payout') {
                    throw new RuntimeException('Source reference reused with a different funding mode/status.');
                }

                $originalAmount = $mode === 'trading_profit'
                    ? $prior->verified_profit_amount
                    : $prior->pool_amount;

                if ($originalAmount === null ||
                    bccomp(self::storedMoney($originalAmount), $inputAmount, self::SCALE) !== 0) {
                    throw new RuntimeException('Source reference reused with a different amount.');
                }

                // Never apply a changed admin percentage to an existing funding.
                return (int) $prior->id;
            }

            $sharingSettings = DB::table('profit_sharing_settings')
                ->where('id', 1)->lockForUpdate()->first();
            $generationSettings = DB::table('generation_pool_settings')
                ->where('id', 1)->lockForUpdate()->first();
            $magicSettings = DB::table('magic_income_settings')
                ->where('id', 1)->lockForUpdate()->first();

            if (!$sharingSettings || !$generationSettings || !$magicSettings) {
                throw new RuntimeException('Funding settings are missing.');
            }

            if (self::enabled($sharingSettings->combined_funding_enabled)) {
                throw new RuntimeException('Combined funding must remain OFF for standalone Magic funding.');
            }

            if (self::enabled($generationSettings->generation_enabled) ||
                self::enabled($magicSettings->magic_enabled)) {
                throw new RuntimeException('Generation and Magic payout switches must remain OFF.');
            }

            $verifiedProfit = null;
            $percentSnapshot = null;

            if ($mode === 'trading_profit') {
                $verifiedProfit = $inputAmount;
                $percentSnapshot = (string) $magicSettings->trading_profit_pool_percent;
                self::validatePercentage($percentSnapshot);

                $poolAmount = bcdiv(
                    bcmul($verifiedProfit, $percentSnapshot, 17),
                    '100',
                    self::SCALE
                );

                if (bccomp($poolAmount, '0', self::SCALE) <= 0) {
                    throw new RuntimeException('Verified profit produces no fundable Magic Pool amount.');
                }

                // The full profit, not merely its Magic percentage, must be
                // verified as available and unencumbered by the operator.
                $requiredCompanyBalance = $verifiedProfit;
            } else {
                $poolAmount = $inputAmount;
                $requiredCompanyBalance = $poolAmount;
            }

            self::validateMoney($poolAmount);

            if (bccomp($companyBalance, $requiredCompanyBalance, self::SCALE) < 0) {
                throw new RuntimeException('Insufficient verified unencumbered company funds.');
            }

            $companyAfter = bcsub($companyBalance, $poolAmount, self::SCALE);
            $magicAfter = bcadd($magicBalance, $poolAmount, self::SCALE);
            $totalMagicAfter = bcadd($totalMagicFunded, $poolAmount, self::SCALE);

            foreach ([$companyAfter, $magicAfter, $totalMagicAfter] as $balance) {
                self::validateStoredBalance($balance);
            }

            $fundingId = DB::table('magic_pool_fundings')->insertGetId([
                'source_reference' => $reference,
                'funding_mode' => $mode,
                'approved_by' => $actualAdmin->id,
                'verified_profit_amount' => $verifiedProfit,
                'magic_percent_snapshot' => $percentSnapshot,
                'pool_amount' => $poolAmount,
                'status' => 'reserved_no_payout',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('company_wallet')->where('id', 1)->update([
                'balance' => $companyAfter,
                'updated_at' => now(),
            ]);

            DB::table('profit_sharing_reserve_accounts')->where('id', 1)->update([
                'magic_balance' => $magicAfter,
                'total_magic_funded' => $totalMagicAfter,
                'updated_at' => now(),
            ]);

            $key = hash('sha256', $reference);

            self::ledger(
                $fundingId,
                'magic_pool_company_out',
                'company_wallet',
                '-' . $poolAmount,
                $companyBalance,
                $companyAfter,
                'magicpool:company:' . $key
            );

            self::ledger(
                $fundingId,
                'magic_pool_reserve_in',
                'magic_pool',
                $poolAmount,
                $magicBalance,
                $magicAfter,
                'magicpool:reserve:' . $key
            );

            return (int) $fundingId;
        }, 3);
    }

    private static function ledger(
        int $fundingId,
        string $entryType,
        string $walletType,
        string $amount,
        string $before,
        string $after,
        string $transactionKey
    ): void {
        DB::table('financial_ledger')->insert([
            'user_id' => null,
            'entry_type' => $entryType,
            'wallet_type' => $walletType,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_type' => 'magic_pool_funding',
            'reference_id' => $fundingId,
            'transaction_key' => $transactionKey,
            'status' => 'completed',
            'description' => 'Internal Magic reserve transfer only; no member payout.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function validateMoney(string $amount): void
    {
        if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) ||
            bccomp($amount, '0', self::SCALE) <= 0 ||
            bccomp($amount, self::MAX_MONEY, self::SCALE) > 0) {
            throw new InvalidArgumentException('Invalid positive USDT amount.');
        }
    }

    private static function validatePercentage(string $percent): void
    {
        if (!preg_match('/\A\d+(?:\.\d{1,9})?\z/D', $percent) ||
            bccomp($percent, '0', 9) < 0 ||
            bccomp($percent, '100', 9) > 0) {
            throw new RuntimeException('Magic Pool percentage must be between 0 and 100.');
        }
    }

    private static function validateStoredBalance(string $amount): void
    {
        if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) ||
            bccomp($amount, '0', self::SCALE) < 0 ||
            bccomp($amount, self::MAX_MONEY, self::SCALE) > 0) {
            throw new RuntimeException('Invalid reserve balance.');
        }
    }

    /**
     * SQLite can return DECIMAL values in scientific notation; PostgreSQL
     * NUMERIC values are already exact strings. Never use floating-point math.
     */
    private static function storedMoney(mixed $value): string
    {
        $text = (string) $value;

        if (!str_contains(strtolower($text), 'e')) {
            return $text;
        }

        if (DB::getDriverName() !== 'sqlite' ||
            !preg_match('/\A(\d+)(?:\.(\d+))?[eE]([+-]?\d{1,3})\z/D', $text, $m)) {
            throw new RuntimeException('Invalid stored monetary representation.');
        }

        $exponent = (int) $m[3];

        if ($exponent < -30 || $exponent > 30) {
            throw new RuntimeException('Invalid stored monetary exponent.');
        }

        $fraction = $m[2] ?? '';
        $digits = $m[1] . $fraction;
        $position = strlen($m[1]) + $exponent;

        if ($position <= 0) {
            $expanded = '0.' . str_repeat('0', -$position) . $digits;
        } elseif ($position >= strlen($digits)) {
            $expanded = $digits . str_repeat('0', $position - strlen($digits));
        } else {
            $expanded = substr($digits, 0, $position) . '.' . substr($digits, $position);
        }

        if (str_contains($expanded, '.')) {
            $expanded = rtrim(rtrim($expanded, '0'), '.');
        }

        return $expanded;
    }

    private static function enabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 't', 'yes', 'on'], true);
    }
}
