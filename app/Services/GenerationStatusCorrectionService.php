<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit audited correction of an inactive-member hold; original business and unlock snapshots stay fixed. */
final class GenerationStatusCorrectionService
{
    public static function settle(User $admin, int $allocationId, string $expectedAmount): int
    {
        if (config('leobot.generation_income_settlement_enabled', false) !== true || (app()->environment('production') && config('leobot.production_financial_approval', false) !== true)) {
            throw new RuntimeException('Generation settlement disabled.');
        }

        return DB::transaction(function () use ($admin, $allocationId, $expectedAmount): int {
            $actor = User::whereKey($admin->id)->lockForUpdate()->first();
            if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new RuntimeException('Active administrator required.');
            }
            $pool = DB::table('generation_pool_accounts')->where('id', 1)->lockForUpdate()->first();
            $allocation = DB::table('generation_member_allocations')->where('id', $allocationId)->lockForUpdate()->first();
            if (! $pool || ! $allocation || bccomp((string) $allocation->share_amount, $expectedAmount, 8) !== 0) {
                throw new RuntimeException('Expected held allocation does not match.');
            }
            $prior = DB::table('generation_income_settlements')->where('allocation_id', $allocationId)->first();
            if ($prior) {
                if (! DB::table('member_security_audits')->where('action', 'generation_inactive_hold_corrected')->whereRaw("details->>'allocation_id' = ?", [(string) $allocationId])->exists()) {
                    throw new RuntimeException('Existing payout is not a status correction.');
                }

                return (int) $prior->id;
            }
            if ($allocation->status !== 'held_unqualified' || $allocation->classification !== 'held' || $allocation->qualification_reason !== 'member_inactive'
                || (int) $allocation->level_number < 1 || (int) $allocation->level_number > min(51, (int) $allocation->unlocked_through_level_snapshot)
                || bccomp((string) $allocation->share_amount, '0', 8) <= 0) {
                throw new RuntimeException('Only an originally unlocked inactive-member hold can be corrected.');
            }
            $fund = DB::table('profit_sharing_fundings')->where('generation_funding_id', $allocation->funding_id)->first();
            $statement = $fund ? DB::table('staking_profit_statements')->where('profit_sharing_funding_id', $fund->id)->first() : null;
            $rows = $statement ? DB::table('staking_profit_allocations')->where('statement_id', $statement->id)->get() : collect();
            $manual = $statement && config('leobot.admin_profit_verification_enabled', false) && StakingProfitService::manualApprovalMatches($statement, $rows);
            $independent = $statement && $statement->verified_by && (int) $statement->verified_by !== (int) $statement->submitted_by && $statement->verification_method !== 'admin_manual_confirmation';
            if (! $fund || $fund->status === 'reversed' || ! $statement || $statement->status !== 'reserved' || (! $manual && ! $independent)) {
                throw new RuntimeException('Original verified trading-profit approval required.');
            }
            $stake = DB::table('staking_positions')->where('user_id', $allocation->user_id)->where('status', 'active')->whereRaw('amount>withdrawn_amount')->where('created_at', '<=', $allocation->created_at)->orderBy('id')->lockForUpdate()->first();
            if (! $stake) {
                throw new RuntimeException('Verified remaining stake must predate the original allocation.');
            }
            StakingReconciliationService::assertOpeningJournals($stake);
            $manualInactive = DB::table('member_security_audits')->where('action', 'admin_member_status')->whereRaw("details->>'user_id' = ?", [(string) $allocation->user_id])->whereRaw("details->>'after' = ?", ['inactive'])->where('created_at', '>=', $stake->created_at)->exists();
            if ($manualInactive) {
                throw new RuntimeException('Deliberate administrator deactivation requires separate review.');
            }
            StakingService::repairMemberReporting($admin, (int) $allocation->user_id);
            $budget = DB::table('generation_level_budgets')->where('id', $allocation->level_budget_id)->lockForUpdate()->first();
            $run = DB::table('generation_allocation_runs')->where('id', $allocation->allocation_run_id)->lockForUpdate()->first();
            $amount = (string) $allocation->share_amount;
            if (! $budget || ! $run || (int) $budget->funding_id !== (int) $allocation->funding_id || (int) $budget->level_number !== (int) $allocation->level_number || (int) $run->funding_id !== (int) $allocation->funding_id
                || bccomp((string) $budget->held_amount, $amount, 8) < 0 || bccomp((string) $pool->held_balance, $amount, 8) < 0 || bccomp((string) $run->held_total, $amount, 8) < 0) {
                throw new RuntimeException('Held reserve cannot fund this correction.');
            }
            DB::table('member_security_audits')->insert(['actor_id' => $admin->id, 'action' => 'generation_inactive_hold_corrected', 'details' => json_encode(['allocation_id' => $allocationId, 'user_id' => $allocation->user_id, 'original_allocation' => $allocation, 'stake_id' => $stake->id, 'amount' => $amount, 'unlocked_level_changed' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            DB::table('generation_level_budgets')->where('id', $budget->id)->update(['held_amount' => bcsub((string) $budget->held_amount, $amount, 8), 'payable_amount' => bcadd((string) $budget->payable_amount, $amount, 8), 'updated_at' => now()]);
            DB::table('generation_allocation_runs')->where('id', $run->id)->update(['held_total' => bcsub((string) $run->held_total, $amount, 8), 'payable_total' => bcadd((string) $run->payable_total, $amount, 8), 'updated_at' => now()]);
            DB::table('generation_pool_accounts')->where('id', 1)->update(['held_balance' => bcsub((string) $pool->held_balance, $amount, 8), 'payable_balance' => bcadd((string) $pool->payable_balance, $amount, 8), 'updated_at' => now()]);
            DB::table('generation_member_allocations')->where('id', $allocationId)->update(['classification' => 'payable', 'qualification_reason' => 'qualified', 'status' => 'qualified_reserved_no_payout', 'updated_at' => now()]);

            return GenerationIncomeSettlementService::settle($admin, $allocationId);
        }, 3);
    }
}
