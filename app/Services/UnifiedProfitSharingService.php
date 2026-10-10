<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Staged unified funding: verified member SELF 67% + global generation 30% + magic 3%.
 * Percentages come from admin settings and must add to EXACTLY 100%.
 * One profit reference, one atomic company wallet transfer, no member payout.
 * Company funds must already be independently verified, unencumbered, and available.
 * No controller, scheduler, or payout route is provided in this phase.
 */
final class UnifiedProfitSharingService
{
    private const SCALE = 8;
    private const RATE_SCALE = 9;
    private const MAX_MONEY = '999999999999.99999999';
    private const MAX_MEMBERS = 10000;

    /**
     * @param array<int,string> $memberProfits User ID => verified profit attributable
     *     to that member in this profit period. Sum MUST equal company profit.
     * @return int Immutable profit_sharing_fundings ID. Retries are idempotent.
     */
    public static function fund(
        User $admin,
        string $companyProfit,
        string $profitReference,
        array $memberProfits
    ): int {
        self::money($companyProfit, true);
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{2,99}\z/D', $profitReference)) {
            throw new InvalidArgumentException('Invalid profit statement reference.');
        }
        $profitReference = strtoupper($profitReference);
        if (count($memberProfits) < 1 || count($memberProfits) > self::MAX_MEMBERS) {
            throw new InvalidArgumentException('A bounded member-profit breakdown is required.');
        }

        $normalized = [];
        $sum = '0.00000000';
        foreach ($memberProfits as $memberId => $amount) {
            if (!is_int($memberId) || $memberId <= 0 || !is_string($amount)) {
                throw new InvalidArgumentException('Member profits must map integer user IDs to decimal strings.');
            }
            self::money($amount, true);
            $normalized[$memberId] = $amount;
            $sum = bcadd($sum, $amount, self::SCALE);
            self::money($sum, true);
        }
        ksort($normalized, SORT_NUMERIC);
        if (bccomp($sum, $companyProfit, self::SCALE) !== 0) {
            throw new RuntimeException('Member profit breakdown must equal the verified company profit.');
        }
        if (!$admin->exists || (int) $admin->getKey() <= 0) {
            throw new AuthorizationException('Valid active admin required.');
        }

        return DB::transaction(function () use ($admin, $companyProfit, $profitReference, $normalized): int {
            $actualAdmin = DB::table('users')->where('id', $admin->getKey())
                ->lockForUpdate()->first(['id', 'username', 'status']);
            if (!$actualAdmin || strtoupper((string) $actualAdmin->username) !== 'ADMIN'
                || (string) $actualAdmin->status !== 'active') {
                throw new AuthorizationException('Active ADMIN approval required.');
            }

            // Global lock order: company wallet -> generation pool -> self/magic reserve.
            $company = DB::table('company_wallet')->where('id', 1)->lockForUpdate()->first();
            $generationAccount = DB::table('generation_pool_accounts')
                ->where('id', 1)->lockForUpdate()->first();
            $reserves = DB::table('profit_sharing_reserve_accounts')
                ->where('id', 1)->lockForUpdate()->first();
            if (!$company || !$generationAccount || !$reserves) {
                throw new RuntimeException('Required reserve accounts are missing.');
            }

            // SQLite tests store DECIMAL as NUMERIC/REAL and may return tiny values
            // in exponent form (e.g. 6.0E-8). Expand the DB representation
            // using string arithmetic ONLY. PostgreSQL NUMERIC remains exact.
            $company->balance = self::storedMoney($company->balance);
            foreach (['balance', 'unallocated_balance', 'held_balance',
                'payable_balance', 'total_funded', 'total_paid'] as $field) {
                $generationAccount->{$field} = self::storedMoney($generationAccount->{$field});
            }
            foreach (['self_balance', 'magic_balance',
                'total_self_funded', 'total_magic_funded'] as $field) {
                $reserves->{$field} = self::storedMoney($reserves->{$field});
            }

            // Check SAME underlying economic profit statement before reading live percentages.
            $prior = DB::table('profit_sharing_fundings')->where('profit_reference', $profitReference)->first();
            if ($prior) {
                if (bccomp(self::storedMoney($prior->verified_profit_amount), $companyProfit, 8) !== 0) {
                    throw new RuntimeException('Profit reference reused with another amount.');
                }
                $rows = DB::table('profit_sharing_self_reserves')
                    ->where('funding_id', $prior->id)->get(['user_id','member_profit_amount']);
                if ($rows->count() !== count($normalized)) {
                    throw new RuntimeException('Profit reference reused with a different member breakdown.');
                }
                foreach ($rows as $row) {
                    $userId = (int) $row->user_id;
                    if (!isset($normalized[$userId]) ||
                        bccomp($normalized[$userId], self::storedMoney($row->member_profit_amount), 8) !== 0) {
                        throw new RuntimeException('Profit reference reused with a different member breakdown.');
                    }
                }
                return (int) $prior->id;
            }
            if (DB::table('generation_profit_fundings')
                ->where('profit_reference', $profitReference)->exists()) {
                throw new RuntimeException('Profit reference already used in legacy generation funding.');
            }

            $settings = DB::table('profit_sharing_settings')->where('id', 1)
                ->lockForUpdate()->first();
            $generation = DB::table('generation_pool_settings')->where('id', 1)
                ->lockForUpdate()->first();
            $magic = DB::table('magic_income_settings')->where('id', 1)
                ->lockForUpdate()->first();
            if (!$settings || !$generation || !$magic) {
                throw new RuntimeException('Profit sharing settings are missing.');
            }
            if (!self::yes($settings->combined_funding_enabled)) {
                throw new RuntimeException('Combined funding is OFF; this phase must be approved before use.');
            }
            if (self::yes($generation->generation_enabled) || self::yes($magic->magic_enabled)) {
                throw new RuntimeException('Generation and Magic payout switches must remain OFF.');
            }

            $selfRate = (string) $settings->self_profit_percent;
            $generationRate = (string) $generation->trading_profit_pool_percent;
            $magicRate = (string) $magic->trading_profit_pool_percent;
            foreach ([$selfRate, $generationRate, $magicRate] as $rate) {
                self::percentage($rate);
            }
            $rateSum = bcadd(bcadd($selfRate, $generationRate, 9), $magicRate, 9);
            if (bccomp($rateSum, '100.000000000', 9) !== 0) {
                throw new RuntimeException('SELF + Generation + Magic must equal exactly 100%.');
            }

            $directRows = [];
            $directTotal = '0.00000000';
            foreach ($normalized as $userId => $memberProfit) {
                if (!DB::table('users')->where('id', $userId)->exists()) {
                    throw new RuntimeException("Unknown profit owner user ID {$userId}.");
                }
                $selfShare = self::percentOf($memberProfit, $selfRate);
                $directTotal = bcadd($directTotal, $selfShare, 8);
                self::nonnegative($directTotal);
                $directRows[] = ['user_id' => $userId,
                    'member_profit_amount' => $memberProfit,
                    'self_share_reserved' => $selfShare];
            }
            $generationAmount = self::percentOf($companyProfit, $generationRate);
            $magicAmount = self::percentOf($companyProfit, $magicRate);
            $reserveTotal = bcadd(bcadd($directTotal, $generationAmount, 8), $magicAmount, 8);
            self::nonnegative($reserveTotal);
            if (bccomp($reserveTotal, '0', 8) <= 0) {
                throw new RuntimeException('Trading profit too small to fund any reserve.');
            }
            $remainder = bcsub($companyProfit, $reserveTotal, 8);
            if (bccomp($remainder, '0', 8) < 0) {
                throw new RuntimeException('Funding exceeds verified profit.');
            }

            foreach (['balance','unallocated_balance','held_balance','payable_balance','total_funded','total_paid'] as $field) {
                self::nonnegative((string) $generationAccount->{$field});
            }
            $genParts = bcadd(bcadd((string) $generationAccount->unallocated_balance,
                (string) $generationAccount->held_balance, 8), (string) $generationAccount->payable_balance, 8);
            if (bccomp($genParts, (string) $generationAccount->balance, 8) !== 0 ||
                bccomp((string) $generationAccount->total_funded, (string) $generationAccount->balance, 8) !== 0 ||
                bccomp((string) $generationAccount->total_paid, '0', 8) !== 0) {
                throw new RuntimeException('Generation reserve accounting is inconsistent.');
            }
            foreach (['self_balance','magic_balance','total_self_funded','total_magic_funded'] as $field) {
                self::nonnegative((string) $reserves->{$field});
            }
            if (bccomp((string) $reserves->self_balance, (string) $reserves->total_self_funded, 8) !== 0 ||
                bccomp((string) $reserves->magic_balance, (string) $reserves->total_magic_funded, 8) !== 0) {
                throw new RuntimeException('SELF/Magic reserve accounting is inconsistent.');
            }
            self::nonnegative((string) $company->balance);
            // Require the entire *verified* profit to be unencumbered and available,
            // not just the 30% generation slice. This is still an operator assertion.
            if (bccomp((string) $company->balance, $companyProfit, 8) < 0) {
                throw new RuntimeException('Insufficient verified unencumbered company funds.');
            }

            $rates = DB::table('level_settings')->orderBy('level_number')
                ->lockForUpdate()->get(['level_number','commission_percentage','is_active']);
            if ($rates->count() !== 51) {
                throw new RuntimeException('Exactly 51 generation rates are required.');
            }
            $rateTotal = '0.000000000';
            $levelRows = [];
            $levelTotal = '0.00000000';
            foreach ($rates as $index => $rate) {
                if ((int) $rate->level_number !== $index + 1) {
                    throw new RuntimeException('Levels must be 1 through 51.');
                }
                $pct = (string) $rate->commission_percentage;
                self::percentage($pct);
                $rateTotal = bcadd($rateTotal, $pct, 9);
                $share = self::percentOf($generationAmount, $pct);
                $levelTotal = bcadd($levelTotal, $share, 8);
                $levelRows[] = [
                    'level_number' => $index + 1,
                    'rate_percent_snapshot' => $pct,
                    'was_active' => self::yes($rate->is_active),
                    'budget_amount' => $share,
                    'unallocated_amount' => $share,
                    'held_amount' => '0.00000000',
                    'payable_amount' => '0.00000000',
                    'paid_amount' => '0.00000000',
                ];
            }
            if (bccomp($rateTotal, '100.000000000', 9) !== 0) {
                throw new RuntimeException('Generation level percentages must total 100%.');
            }
            $levelDust = bcsub($generationAmount, $levelTotal, 8);
            if (bccomp($levelDust, '0', 8) < 0) {
                throw new RuntimeException('Level budgets exceed the generation reserve.');
            }

            $beforeCompany = (string) $company->balance;
            $afterCompany = bcsub($beforeCompany, $reserveTotal, 8);
            $beforeGen = (string) $generationAccount->balance;
            $afterGen = bcadd($beforeGen, $generationAmount, 8);
            $beforeSelf = (string) $reserves->self_balance;
            $afterSelf = bcadd($beforeSelf, $directTotal, 8);
            $beforeMagic = (string) $reserves->magic_balance;
            $afterMagic = bcadd($beforeMagic, $magicAmount, 8);
            foreach ([
                $afterGen, $afterSelf, $afterMagic,
                bcadd((string) $generationAccount->unallocated_balance, $generationAmount, 8),
                bcadd((string) $generationAccount->total_funded, $generationAmount, 8),
            ] as $result) {
                self::nonnegative($result);
            }

            $genFundingId = DB::table('generation_profit_fundings')->insertGetId([
                'profit_reference' => $profitReference,
                'approved_by' => $actualAdmin->id,
                'verified_profit_amount' => $companyProfit,
                'pool_percentage_snapshot' => $generationRate,
                'pool_amount' => $generationAmount,
                'rounding_unallocated' => $levelDust,
                'status' => 'funded_unallocated',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($levelRows as $row) {
                DB::table('generation_level_budgets')->insert(array_merge($row, [
                    'funding_id' => $genFundingId,
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            }
            $fundingId = DB::table('profit_sharing_fundings')->insertGetId([
                'profit_reference' => $profitReference,
                'approved_by' => $actualAdmin->id,
                'generation_funding_id' => $genFundingId,
                'verified_profit_amount' => $companyProfit,
                'self_percent_snapshot' => $selfRate,
                'generation_percent_snapshot' => $generationRate,
                'magic_percent_snapshot' => $magicRate,
                'self_amount' => $directTotal,
                'generation_amount' => $generationAmount,
                'magic_amount' => $magicAmount,
                'funded_total' => $reserveTotal,
                'company_rounding_remainder' => $remainder,
                'status' => 'reserved_no_payout',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($directRows as $row) {
                DB::table('profit_sharing_self_reserves')->insert(array_merge($row, [
                    'funding_id' => $fundingId, 'status' => 'reserved_no_payout',
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            }

            DB::table('company_wallet')->where('id', 1)->update([
                'balance' => $afterCompany, 'updated_at' => now(),
            ]);
            DB::table('generation_pool_accounts')->where('id', 1)->update([
                'balance' => $afterGen,
                'unallocated_balance' => bcadd((string) $generationAccount->unallocated_balance,
                    $generationAmount, 8),
                'total_funded' => bcadd((string) $generationAccount->total_funded,
                    $generationAmount, 8),
                'updated_at' => now(),
            ]);
            DB::table('profit_sharing_reserve_accounts')->where('id', 1)->update([
                'self_balance' => $afterSelf, 'magic_balance' => $afterMagic,
                'total_self_funded' => bcadd((string) $reserves->total_self_funded, $directTotal, 8),
                'total_magic_funded' => bcadd((string) $reserves->total_magic_funded, $magicAmount, 8),
                'updated_at' => now(),
            ]);

            $key = hash('sha256', $profitReference);
            self::ledger($fundingId, 'profit_sharing_company_out', 'company_wallet',
                '-' . $reserveTotal, $beforeCompany, $afterCompany, 'profitsharing:company:' . $key);
            self::ledger($fundingId, 'profit_sharing_generation_in', 'generation_pool',
                $generationAmount, $beforeGen, $afterGen, 'profitsharing:generation:' . $key);
            self::ledger($fundingId, 'profit_sharing_self_reserve_in', 'self_reserve',
                $directTotal, $beforeSelf, $afterSelf, 'profitsharing:self:' . $key);
            self::ledger($fundingId, 'profit_sharing_magic_reserve_in', 'magic_pool',
                $magicAmount, $beforeMagic, $afterMagic, 'profitsharing:magic:' . $key);
            return (int) $fundingId;
        }, 3);
    }

    private static function ledger(
        int $fundingId, string $type, string $wallet, string $amount,
        string $before, string $after, string $key
    ): void {
        if (bccomp($amount, '0', 8) === 0) {
            return;
        }
        DB::table('financial_ledger')->insert([
            'user_id' => null,
            'entry_type' => $type, 'wallet_type' => $wallet,
            'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after,
            'reference_type' => 'profit_sharing_funding', 'reference_id' => $fundingId,
            'transaction_key' => $key,
            'status' => 'completed',
            'description' => 'Staged internal reserve funding only; absolutely no member payout.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private static function percentage(string $percent): void
    {
        if (!preg_match('/\A\d+(?:\.\d{1,9})?\z/D', $percent) ||
            bccomp($percent, '0', 9) < 0 || bccomp($percent, '100', 9) > 0) {
            throw new RuntimeException('Percentage outside valid 0-100 range.');
        }
    }

    private static function money(string $amount, bool $positive): void
    {
        if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) ||
            bccomp($amount, $positive ? '0' : '-0.00000001', 8) <= 0 ||
            bccomp($amount, self::MAX_MONEY, 8) > 0) {
            throw new InvalidArgumentException('Invalid USDT amount.');
        }
    }

    /**
     * Expand scientific notation emitted by SQLite DECIMAL storage, without
     * converting money through PHP float. User-entered amounts never use this.
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
        $decimalPosition = strlen($m[1]) + $exponent;
        if ($decimalPosition <= 0) {
            $expanded = '0.' . str_repeat('0', -$decimalPosition) . $digits;
        } elseif ($decimalPosition >= strlen($digits)) {
            $expanded = $digits . str_repeat('0', $decimalPosition - strlen($digits));
        } else {
            $expanded = substr($digits, 0, $decimalPosition) . '.' .
                substr($digits, $decimalPosition);
        }
        if (str_contains($expanded, '.')) {
            $expanded = rtrim(rtrim($expanded, '0'), '.');
        }
        // No rounding: amounts with more than 8 meaningful decimal places
        // are rejected by nonnegative() at their point of use.
        return $expanded;
    }

    private static function nonnegative(string $value): void
    {
        if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $value) ||
            bccomp($value, '0', 8) < 0 || bccomp($value, self::MAX_MONEY, 8) > 0) {
            throw new RuntimeException('Invalid reserve balance.');
        }
    }

    private static function percentOf(string $amount, string $percent): string
    {
        return bcdiv(bcmul($amount, $percent, 17), '100', 8);
    }

    private static function yes(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value),
            ['1', 'true', 't', 'yes', 'on'], true);
    }
}
