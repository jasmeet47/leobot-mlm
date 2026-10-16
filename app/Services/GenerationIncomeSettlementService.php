<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** One approved payable 51-level allocation -> member wallet; never settle held shares. */
final class GenerationIncomeSettlementService
{
    public static function settle(User $admin, int $allocationId): int
    {
        if (config('leobot.generation_income_settlement_enabled', false) !== true ||
            (app()->environment('production') && config('leobot.production_financial_approval', false) !== true)) {
            throw new RuntimeException('Generation settlement disabled.');
        }
        if ($allocationId < 1) throw new RuntimeException('Invalid allocation ID.');
        return DB::transaction(function () use ($admin, $allocationId): int {
            // Shared pool lock serializes settlements and allocations.
            $pool = DB::table('generation_pool_accounts')->where('id', 1)->lockForUpdate()->first();
            if (!$pool) throw new RuntimeException('Missing generation reserve.');
            $actor = DB::table('users')->where('id', $admin->getKey())->lockForUpdate()->first();
            if (!$actor || !in_array((string) $actor->role, ['admin','super_admin'], true) ||
                (string) $actor->status !== 'active' || self::blocked($actor)) {
                throw new RuntimeException('Authorized active admin required.');
            }
            $allocation = DB::table('generation_member_allocations')->where('id', $allocationId)->lockForUpdate()->first();
            if (!$allocation) throw new RuntimeException('Allocation missing.');
            $prior = DB::table('generation_income_settlements')->where('allocation_id', $allocationId)->first();
            if ($prior) return (int) $prior->id;
            if ($allocation->classification !== 'payable' || $allocation->status !== 'qualified_reserved_no_payout' ||
                $allocation->qualification_reason !== 'qualified' ||
                (int) $allocation->level_number < 1 || (int) $allocation->level_number > 51) {
                throw new RuntimeException('Allocation not payable.');
            }
            $member = DB::table('users')->where('id', $allocation->user_id)->lockForUpdate()->first();
            if (!$member || (string) $member->status !== 'active' || self::blocked($member)) {
                throw new RuntimeException('Member ineligible at settlement.');
            }
            $budget = DB::table('generation_level_budgets')->where('id', $allocation->level_budget_id)->lockForUpdate()->first();
            $amount = (string) $allocation->share_amount;
            if (!$budget || (int) $budget->funding_id !== (int) $allocation->funding_id ||
                !preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount,'0',8) <= 0) {
                throw new RuntimeException('Invalid allocation amount or budget.');
            }
            $availableBudget = bcsub((string)$budget->payable_amount,(string)$budget->paid_amount,8);
            if (bccomp($availableBudget,$amount,8) < 0 || bccomp((string)$pool->payable_balance,$amount,8) < 0 ||
                bccomp((string)$pool->balance,$amount,8) < 0) {
                throw new RuntimeException('Insufficient payable reserve.');
            }
            $sum = bcadd(bcadd((string)$pool->unallocated_balance,(string)$pool->held_balance,8),(string)$pool->payable_balance,8);
            if (bccomp($sum,(string)$pool->balance,8) !== 0 ||
                bccomp(bcsub((string)$pool->total_funded,(string)$pool->total_paid,8),(string)$pool->balance,8) !== 0) {
                throw new RuntimeException('Generation pool reconciliation failed.');
            }
            $key = 'generation:allocation:'.$allocationId;
            WalletService::credit((int)$member->id,'available_balance',$amount,'generation_income_settlement',$key,[
                'level'=>(int)$allocation->level_number,'reference_type'=>'generation_member_allocation',
                'reference_id'=>$allocationId,'description'=>'51-level verified allocation settlement'
            ]);
            $lifetime=bcadd((string)$member->lifetime_income,$amount,8);
            if (bccomp($lifetime,'999999999999.99999999',8)>0) {
                throw new RuntimeException('Member lifetime income limit exceeded.');
            }
            DB::table('users')->where('id',$member->id)->update(['lifetime_income'=>$lifetime,'updated_at'=>now()]);
            DB::table('generation_level_budgets')->where('id',$budget->id)->update([
                'paid_amount'=>bcadd((string)$budget->paid_amount,$amount,8),'updated_at'=>now()
            ]);
            DB::table('generation_pool_accounts')->where('id',1)->update([
                'balance'=>bcsub((string)$pool->balance,$amount,8),
                'payable_balance'=>bcsub((string)$pool->payable_balance,$amount,8),
                'total_paid'=>bcadd((string)$pool->total_paid,$amount,8),'updated_at'=>now()
            ]);
            DB::table('generation_member_allocations')->where('id',$allocationId)->update([
                'status'=>'paid_to_wallet','updated_at'=>now()
            ]);
            return (int) DB::table('generation_income_settlements')->insertGetId([
                'allocation_id'=>$allocationId,'user_id'=>$member->id,'approved_by'=>$actor->id,
                'amount'=>$amount,'transaction_key'=>$key,'created_at'=>now(),'updated_at'=>now()
            ]);
        }, 3);
    }
    private static function blocked(object $user): bool
    {
        return in_array($user->is_blocked ?? false,[true,1,'1','t','true'],true);
    }
}
