<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit correction of one legacy receipt; original journals and snapshots are retained. */
final class LegacyIncomeCorrectionService
{
    public static function correct(User $admin, int $statementId, string $expectedProfit, string $expectedPaid): int
    {
        if (! config('leobot.admin_profit_verification_enabled') || ! config('leobot.self_income_settlement_enabled')
            || (app()->environment('production') && ! config('leobot.production_financial_approval'))) {
            throw new RuntimeException('Correction and payout controls are OFF.');
        }

        return DB::transaction(function () use ($admin, $statementId, $expectedProfit, $expectedPaid): int {
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            } elseif (! in_array(DB::selectOne('SHOW transaction_isolation')->transaction_isolation, ['repeatable read', 'serializable'], true)) {
                throw new RuntimeException('Correction requires snapshot isolation.');
            }
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new RuntimeException('Active administrator required.');
            }
            $statement = DB::table('staking_profit_statements')->where('id', $statementId)->lockForUpdate()->first();
            $prior = DB::table('member_security_audits')->where('action', 'legacy_income_corrected')->whereRaw("details->>'statement_id' = ?", [(string) $statementId])->first();
            if ($prior) {
                $details = json_decode($prior->details, true, 512, JSON_THROW_ON_ERROR);
                if (bccomp($details['original_profit'], $expectedProfit, 8) !== 0 || bccomp($details['reversed_paid'], $expectedPaid, 8) !== 0) {
                    throw new RuntimeException('Correction retry does not match the approved amounts.');
                }

                return (int) $details['replacement_statement_id'];
            }
            if (! $statement || $statement->status !== 'reserved' || $statement->distribution_version !== 'unified_67_30_3'
                || bccomp((string) $statement->amount, $expectedProfit, 8) !== 0 || MagicSplitPolicy::version() !== MagicSplitPolicy::VERSION
                || ! StakingProfitService::manualApprovalMatches($statement, DB::table('staking_profit_allocations')->where('statement_id', $statementId)->get())) {
                throw new RuntimeException('Approved legacy statement and current policy required.');
            }
            $period = DB::table('income_distribution_requests')->where('statement_id', $statementId)->first();
            if (! $period || $period->period !== 'weekly') {
                throw new RuntimeException('Only the approved weekly correction is supported.');
            }
            if (! StakingReconciliationService::inspect()['accounting_passed']) {
                throw new RuntimeException('Pre-correction accounting failed.');
            }
            if (! config('leobot_magic.magic_matrix_settlement_enabled') || (app()->environment('production') && ! config('leobot_magic.production_financial_approval'))
                || ! DB::table('magic_matrix_settings')->where('id', 1)->value('settlement_enabled') || ! DB::table('magic_income_settings')->where('id', 1)->value('magic_enabled')) {
                throw new RuntimeException('Matrix and Equal settlement controls must be ON for correction.');
            }
            $company = DB::table('company_wallet')->where('id', 1)->lockForUpdate()->first();
            $generation = DB::table('generation_pool_accounts')->where('id', 1)->lockForUpdate()->first();
            $reserve = DB::table('profit_sharing_reserve_accounts')->where('id', 1)->lockForUpdate()->first();
            $funding = DB::table('profit_sharing_fundings')->where('id', $statement->profit_sharing_funding_id)->lockForUpdate()->first();
            if (! $company || ! $generation || ! $reserve || ! $funding || $funding->magic_distribution_version !== 'matrix_v1'
                || DB::table('generation_member_allocations')->where('funding_id', $funding->generation_funding_id)->exists()
                || DB::table('magic_equal_settlement_runs')->where('funding_id', $funding->id)->exists()) {
                throw new RuntimeException('Correction requires legacy funding with no generation allocations or Equal payout.');
            }
            $selfRows = DB::table('profit_sharing_self_reserves')->where('funding_id', $funding->id)->lockForUpdate()->get();
            $credits = DB::table('financial_ledger')->where('status', 'completed')->where('wallet_type', 'available_balance')->where('amount', '>', 0)
                ->where(function ($query) use ($selfRows, $funding): void {
                    $query->where(function ($query) use ($selfRows): void {
                        $query->where('entry_type', 'self_income_settlement')->where('reference_type', 'profit_sharing_self_reserve')->whereIn('reference_id', $selfRows->pluck('id'));
                    })->orWhere(function ($query) use ($funding): void {
                        $query->where('entry_type', 'magic_income_settlement')->where('reference_type', 'profit_sharing_funding')->where('reference_id', $funding->id);
                    });
                })->orderBy('user_id')->orderBy('id')->lockForUpdate()->get();
            $paid = '0';
            $selfPaid = '0';
            $magicPaid = '0';
            foreach ($credits as $credit) {
                $paid = bcadd($paid, (string) $credit->amount, 8);
                if ($credit->entry_type === 'self_income_settlement') {
                    $selfPaid = bcadd($selfPaid, (string) $credit->amount, 8);
                } else {
                    $magicPaid = bcadd($magicPaid, (string) $credit->amount, 8);
                }
            }
            $settledSelf = (string) DB::table('self_income_settlements')->whereIn('self_reserve_id', $selfRows->pluck('id'))->sum('amount');
            $entitlements = DB::table('magic_income_entitlements')->where('funding_id', $funding->id)->lockForUpdate()->get();
            $settledMagic = $entitlements->reduce(fn (string $sum, $row): string => bcadd($sum, (string) $row->paid_amount, 8), '0');
            if (bccomp($paid, $expectedPaid, 8) !== 0 || bccomp($selfPaid, $settledSelf, 8) !== 0 || bccomp($magicPaid, $settledMagic, 8) !== 0) {
                throw new RuntimeException('Expected paid credits do not match immutable settlement records.');
            }
            foreach ($credits->groupBy('user_id') as $userId => $rows) {
                $member = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
                $amount = $rows->reduce(fn (string $sum, $row): string => bcadd($sum, (string) $row->amount, 8), '0');
                if (! $member || bccomp((string) $member->available_balance, $amount, 8) < 0 || bccomp((string) $member->lifetime_income, $amount, 8) < 0) {
                    throw new RuntimeException('A credited member has insufficient available balance or lifetime income. No correction applied.');
                }
                foreach ($rows as $credit) {
                    WalletService::debit((int) $userId, 'available_balance', (string) $credit->amount, 'income_correction_reversal', 'income:reverse:'.$credit->id,
                        ['reference_type' => 'financial_ledger', 'reference_id' => $credit->id, 'description' => 'Audited reversal for weekly statement '.$statementId]);
                }
                DB::table('users')->where('id', $userId)->update(['lifetime_income' => bcsub((string) $member->lifetime_income, $amount, 8), 'updated_at' => now()]);
            }
            foreach ($entitlements->where('paid_amount', '>', 0)->groupBy('cycle_id') as $cycleId => $rows) {
                $cycle = DB::table('magic_matrix_cycles')->where('id', $cycleId)->lockForUpdate()->first();
                $amount = $rows->reduce(fn (string $sum, $row): string => bcadd($sum, (string) $row->paid_amount, 8), '0');
                if (! $cycle || bccomp((string) $cycle->magic_paid_usdt, $amount, 8) < 0 || ! in_array($cycle->status, ['active', 'capped'], true)) {
                    throw new RuntimeException('Magic cycle is not safely reversible.');
                }
                $after = bcsub((string) $cycle->magic_paid_usdt, $amount, 8);
                DB::table('magic_matrix_cycles')->where('id', $cycleId)->update(['magic_paid_usdt' => $after, 'status' => 'active', 'cap_reached_at' => null, 'updated_at' => now()]);
            }
            foreach ([['self_reserve', 'self_balance', 'total_self_settled', $selfPaid], ['magic_pool', 'magic_balance', 'total_magic_settled', $magicPaid]] as [$wallet, $balanceField, $settledField, $amount]) {
                self::journal($statementId, $wallet, $amount, (string) $reserve->{$balanceField}, 'payout-return');
                $reserve->{$balanceField} = bcadd((string) $reserve->{$balanceField}, $amount, 8);
                $reserve->{$settledField} = self::subtract((string) $reserve->{$settledField}, $amount);
            }
            foreach ([['self_reserve', 'self_balance', 'total_self_funded', (string) $funding->self_amount], ['magic_pool', 'magic_balance', 'total_magic_funded', (string) $funding->magic_amount]] as [$wallet, $balanceField, $fundedField, $amount]) {
                self::journal($statementId, $wallet, '-'.$amount, (string) $reserve->{$balanceField}, 'unfund');
                $reserve->{$balanceField} = self::subtract((string) $reserve->{$balanceField}, $amount);
                $reserve->{$fundedField} = self::subtract((string) $reserve->{$fundedField}, $amount);
            }
            $reserveChanges = [];
            foreach (['self_balance', 'magic_balance', 'total_self_settled', 'total_magic_settled', 'total_self_funded', 'total_magic_funded'] as $field) {
                $reserveChanges[$field] = $reserve->{$field};
            }
            DB::table('profit_sharing_reserve_accounts')->where('id', 1)->update($reserveChanges + ['updated_at' => now()]);
            $generationAmount = (string) $funding->generation_amount;
            self::journal($statementId, 'generation_pool', '-'.$generationAmount, (string) $generation->balance, 'unfund');
            $generationChanges = [];
            foreach (['balance', 'unallocated_balance', 'total_funded'] as $field) {
                $generationChanges[$field] = self::subtract((string) $generation->{$field}, $generationAmount);
            }
            DB::table('generation_pool_accounts')->where('id', 1)->update($generationChanges + ['updated_at' => now()]);
            $balance = self::journal($statementId, 'company_wallet', (string) $funding->funded_total, (string) $company->balance, 'unfund');
            $balance = self::journal($statementId, 'company_wallet', '-'.(string) $statement->amount, $balance, 'receipt-reversal');
            DB::table('company_wallet')->where('id', 1)->update(['balance' => $balance, 'updated_at' => now()]);
            self::journal($statementId, 'external_trading_profit', (string) $statement->amount, '0', 'source-reversal');
            DB::table('staking_profit_statements')->where('id', $statementId)->update(['status' => 'reversed', 'updated_at' => now()]);
            DB::table('profit_sharing_fundings')->where('id', $funding->id)->update(['status' => 'reversed', 'updated_at' => now()]);
            DB::table('generation_profit_fundings')->where('id', $funding->generation_funding_id)->update(['status' => 'reversed', 'updated_at' => now()]);
            DB::table('profit_sharing_self_reserves')->where('funding_id', $funding->id)->update(['status' => 'reversed', 'updated_at' => now()]);
            DB::table('magic_income_entitlements')->where('funding_id', $funding->id)->update(['status' => 'reversed', 'updated_at' => now()]);
            $replacement = IncomeDistributionService::submit($admin, (string) $statement->amount, 'CORRECTION-WEEKLY-'.$statementId, 'weekly', substr($period->period_date, 0, 10), substr($period->period_end_date, 0, 10));
            StakingProfitService::verifyByAdmin($admin, $replacement);
            IncomeDistributionService::distribute($admin, $replacement);
            $replacementFunding = DB::table('staking_profit_statements')->where('id', $replacement)->value('profit_sharing_funding_id');
            if (! DB::table('magic_equal_settlement_runs')->where('funding_id', $replacementFunding)->exists()) {
                throw new RuntimeException('Replacement Equal settlement is missing. Entire correction rolled back.');
            }
            DB::table('member_security_audits')->insert(['actor_id' => $admin->id, 'action' => 'legacy_income_corrected', 'details' => json_encode(['statement_id' => $statementId, 'replacement_statement_id' => $replacement, 'original_profit' => (string) $statement->amount, 'reversed_paid' => $paid, 'credit_rows' => $credits->count(), 'policy' => MagicSplitPolicy::VERSION], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            if (! StakingReconciliationService::inspect()['accounting_passed']) {
                throw new RuntimeException('Post-correction reconciliation failed. Entire correction rolled back.');
            }

            return $replacement;
        }, 3);
    }

    private static function subtract(string $before, string $amount): string
    {
        $after = bcsub($before, $amount, 8);
        if (bccomp($after, '0', 8) < 0) {
            throw new RuntimeException('Correction would overdraw a reserve.');
        }

        return $after;
    }

    private static function journal(int $statementId, string $wallet, string $amount, string $before, string $suffix): string
    {
        $after = bcadd($before, $amount, 8);
        if (bccomp($after, '0', 8) < 0) {
            throw new RuntimeException('Correction would overdraw a reserve journal.');
        }
        if (bccomp($amount, '0', 8) !== 0) {
            DB::table('financial_ledger')->insert(['user_id' => null, 'entry_type' => 'income_correction_reserve', 'wallet_type' => $wallet, 'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after,
                'transaction_key' => 'income:correction:'.$statementId.':'.$wallet.':'.$suffix, 'reference_type' => 'staking_profit_statement', 'reference_id' => $statementId, 'status' => 'completed', 'description' => 'Audited reversal of legacy weekly receipt; not new profit', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $after;
    }
}
