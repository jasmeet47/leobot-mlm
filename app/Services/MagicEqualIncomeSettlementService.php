<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** A funding-scoped, immutable equal-share cohort. Capped or ineligible shares stay held. */
final class MagicEqualIncomeSettlementService
{
    public static function settle(User $admin, int $fundingId): int
    {
        if (! config('leobot_magic.magic_matrix_settlement_enabled', false) || (app()->environment('production') && ! config('leobot_magic.production_financial_approval', false))) {
            throw new RuntimeException('Magic settlement controls are OFF.');
        }

        return DB::transaction(function () use ($admin, $fundingId): int {
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new RuntimeException('Active administrator required.');
            }
            $reserve = DB::table('profit_sharing_reserve_accounts')->where('id', 1)->lockForUpdate()->first();
            $matrix = DB::table('magic_matrix_settings')->where('id', 1)->lockForUpdate()->first();
            if (! $reserve || ! $matrix || ! $matrix->settlement_enabled || ! DB::table('magic_income_settings')->where('id', 1)->value('magic_enabled')) {
                throw new RuntimeException('Magic settlement switches are OFF.');
            }
            $funding = DB::table('profit_sharing_fundings')->where('id', $fundingId)->first();
            if (! $funding || $funding->magic_distribution_version !== 'matrix_equal_v2' || bccomp((string) $funding->equal_percent_snapshot, '2', 9) !== 0 || bccomp((string) $funding->matrix_percent_snapshot, '3', 9) !== 0 || bccomp((string) $funding->magic_percent_snapshot, '5', 9) !== 0) {
                throw new RuntimeException('Approved Matrix/Equal funding snapshot required.');
            }
            if (app()->environment('production')) {
                $statement = DB::table('staking_profit_statements')->where('profit_sharing_funding_id', $fundingId)->where('status', 'reserved')->where('distribution_version', MagicSplitPolicy::VERSION)->first();
                $manual = $statement && config('leobot.admin_profit_verification_enabled', false) && StakingProfitService::manualApprovalMatches($statement, DB::table('staking_profit_allocations')->where('statement_id', $statement->id)->get());
                $independent = $statement && $statement->verified_by && (int) $statement->verified_by !== (int) $statement->submitted_by && $statement->verification_method !== 'admin_manual_confirmation';
                if (! $manual && ! $independent) {
                    throw new RuntimeException('Verified trading-profit approval required.');
                }
            }
            $prior = DB::table('magic_equal_settlement_runs')->where('funding_id', $fundingId)->first();
            if ($prior) {
                return (int) $prior->id;
            }
            if (bccomp(bcadd((string) $reserve->magic_balance, (string) $reserve->total_magic_settled, 8), (string) $reserve->total_magic_funded, 8) !== 0) {
                throw new RuntimeException('Magic reserve does not reconcile.');
            }
            $members = json_decode($funding->equal_members_snapshot, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($members) || count($members) > 1000) {
                throw new RuntimeException('Invalid equal-share cohort.');
            }
            $budget = bcdiv(bcmul((string) $funding->verified_profit_amount, '2', 17), '100', 8);
            $shares = $members && bccomp($budget, '0', 8) > 0 ? IncomeDistributionService::allocate($budget, array_fill_keys(array_keys($members), '1')) : [];
            $run = DB::table('magic_equal_settlement_runs')->insertGetId(['funding_id' => $fundingId, 'approved_by' => $admin->id, 'budget_amount' => $budget, 'paid_amount' => '0', 'held_amount' => $budget, 'member_count' => count($members), 'created_at' => now(), 'updated_at' => now()]);
            $total = '0.00000000';
            foreach ($shares as $id => $nominal) {
                $member = DB::table('users')->where('id', $id)->lockForUpdate()->first();
                $cycle = DB::table('magic_matrix_cycles')->where('id', $members[$id])->where('user_id', $id)->lockForUpdate()->first();
                $paid = '0.00000000';
                $status = 'held_ineligible';
                if ($member && $cycle && $cycle->status === 'active' && MagicQualificationService::inspect((int) $id)['eligible']) {
                    $remaining = bcsub((string) $cycle->earning_cap_usdt, (string) $cycle->magic_paid_usdt, 8);
                    if (bccomp($remaining, '0', 8) < 0) {
                        throw new RuntimeException('Magic cap does not reconcile.');
                    }
                    $paid = bccomp($remaining, $nominal, 8) < 0 ? $remaining : $nominal;
                    $status = bccomp($paid, $nominal, 8) === 0 ? 'credited' : 'held_cap';
                }
                $key = 'magic:equal:'.$fundingId.':'.$id;
                if (bccomp($paid, '0', 8) > 0) {
                    if (bccomp(bcadd($total, $paid, 8), (string) $reserve->magic_balance, 8) > 0) {
                        throw new RuntimeException('Insufficient Magic reserve.');
                    }
                    WalletService::credit((int) $id, 'available_balance', $paid, 'magic_income_settlement', $key, ['reference_type' => 'profit_sharing_funding', 'reference_id' => $fundingId, 'description' => 'Verified equal-share Magic income']);
                    $lifetime = bcadd((string) $member->lifetime_income, $paid, 8);
                    if (bccomp($lifetime, '999999999999.99999999', 8) > 0) {
                        throw new RuntimeException('Lifetime income limit exceeded.');
                    }
                    DB::table('users')->where('id', $id)->update(['lifetime_income' => $lifetime, 'updated_at' => now()]);
                    $cyclePaid = bcadd((string) $cycle->magic_paid_usdt, $paid, 8);
                    $capped = bccomp($cyclePaid, (string) $cycle->earning_cap_usdt, 8) === 0;
                    DB::table('magic_matrix_cycles')->where('id', $cycle->id)->update(['magic_paid_usdt' => $cyclePaid, 'status' => $capped ? 'capped' : 'active', 'cap_reached_at' => $capped ? now() : $cycle->cap_reached_at, 'updated_at' => now()]);
                    $total = bcadd($total, $paid, 8);
                }
                DB::table('magic_equal_entitlements')->insert(['run_id' => $run, 'user_id' => $id, 'cycle_id' => $cycle?->id, 'nominal_amount' => $nominal, 'paid_amount' => $paid, 'held_amount' => bcsub($nominal, $paid, 8), 'status' => $status, 'transaction_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            }
            if (bccomp($total, $budget, 8) > 0) {
                throw new RuntimeException('Equal payout exceeds its fixed funding budget.');
            }
            if (bccomp($total, '0', 8) > 0) {
                ReserveSettlementJournal::debit('magic_pool', $total, (string) $reserve->magic_balance, 'magic:equal:reserve:'.$fundingId, 'profit_sharing_funding', $fundingId);
            }
            DB::table('profit_sharing_reserve_accounts')->where('id', 1)->update(['magic_balance' => bcsub((string) $reserve->magic_balance, $total, 8), 'total_magic_settled' => bcadd((string) $reserve->total_magic_settled, $total, 8), 'updated_at' => now()]);
            DB::table('magic_equal_settlement_runs')->where('id', $run)->update(['paid_amount' => $total, 'held_amount' => bcsub($budget, $total, 8), 'updated_at' => now()]);

            return (int) $run;
        }, 3);
    }
}
