<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit, gated, atomic settlement of one already-reserved SELF share. */
final class SelfIncomeSettlementService
{
    public static function settle(User $admin, int $reserveId): int
    {
        if (config('leobot.self_income_settlement_enabled', false) !== true
            || (app()->environment('production') && config('leobot.production_financial_approval', false) !== true)) {
            throw new RuntimeException('Self income settlement is disabled.');
        }
        if ($reserveId <= 0) {
            throw new RuntimeException('Invalid reserve ID.');
        }
        return DB::transaction(function () use ($admin, $reserveId): int {
            $actor = DB::table('users')->where('id', $admin->getKey())->lockForUpdate()->first();
            if (!$actor || !in_array((string) $actor->role, ['admin', 'super_admin'], true)
                || (string) $actor->status !== 'active' || self::blocked($actor)) {
                throw new RuntimeException('Active unblocked administrator required.');
            }
            // Lock global reserve first; this serializes settlements against other settlements.
            $account = DB::table('profit_sharing_reserve_accounts')->where('id', 1)->lockForUpdate()->first();
            if (!$account) {
                throw new RuntimeException('Self reserve account missing.');
            }
            $reserve = DB::table('profit_sharing_self_reserves')->where('id', $reserveId)->lockForUpdate()->first();
            if (!$reserve) {
                throw new RuntimeException('Self reserve missing.');
            }
            $prior = DB::table('self_income_settlements')->where('self_reserve_id', $reserveId)->first();
            if ($prior) {
                return (int) $prior->id;
            }
            if ((string) $reserve->status !== 'reserved_no_payout') {
                throw new RuntimeException('Reserve not eligible for settlement.');
            }
            $member = DB::table('users')->where('id', $reserve->user_id)->lockForUpdate()->first();
            if (!$member || (string) $member->status !== 'active' || self::blocked($member)) {
                throw new RuntimeException('Member not eligible at settlement.');
            }
            $amount = (string) $reserve->self_share_reserved;
            if (bccomp(bcadd((string)$account->self_balance, (string)$account->total_self_settled,8),
                    (string)$account->total_self_funded,8) !== 0) {
                throw new RuntimeException('Self reserve reconciliation failed.');
            }
            if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0
                || bccomp((string) $account->self_balance, $amount, 8) < 0) {
                throw new RuntimeException('Invalid or insufficient self reserve.');
            }
            WalletService::credit((int) $member->id, 'available_balance', $amount,
                'self_income_settlement', 'self:reserve:'.$reserveId,
                ['reference_type' => 'profit_sharing_self_reserve', 'reference_id' => $reserveId,
                 'description' => 'Settled verified self income reserve']);
            $lifetime=bcadd((string)$member->lifetime_income,$amount,8);
            if (bccomp($lifetime,'999999999999.99999999',8)>0) {
                throw new RuntimeException('Member lifetime income limit exceeded.');
            }
            DB::table('users')->where('id',$member->id)->update(['lifetime_income'=>$lifetime,'updated_at'=>now()]);
            DB::table('profit_sharing_reserve_accounts')->where('id', 1)->update([
                'self_balance' => bcsub((string) $account->self_balance, $amount, 8),
                'total_self_settled' => bcadd((string) $account->total_self_settled, $amount, 8),
                'updated_at' => now(),
            ]);
            DB::table('profit_sharing_self_reserves')->where('id', $reserveId)->update([
                'status' => 'paid_to_wallet', 'updated_at' => now(),
            ]);
            return (int) DB::table('self_income_settlements')->insertGetId([
                'self_reserve_id' => $reserveId, 'user_id' => $member->id, 'approved_by' => $actor->id,
                'amount' => $amount, 'transaction_key' => 'self:reserve:'.$reserveId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    private static function blocked(object $user): bool
    {
        return in_array($user->is_blocked ?? false, [true, 1, '1', 't', 'true'], true);
    }
}
