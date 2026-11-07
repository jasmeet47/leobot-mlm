<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StakingMemberEligibility
{
    public static function canStake(User $member, string $wallet, string $amount): bool
    {
        if ($member->role !== 'member' || ! in_array($member->status, ['active', 'inactive'], true) || $member->is_blocked || $member->deleted_at !== null) {
            return false;
        }
        if ($member->status === 'active') {
            return true;
        }
        if ($wallet !== 'activation_balance') {
            return false;
        }
        $verified = (string) DB::table('financial_ledger as l')->join('member_deposit_requests as d', function ($join): void {
            $join->on('d.id', '=', 'l.reference_id')->on('d.user_id', '=', 'l.user_id');
        })
            ->where('l.user_id', $member->id)->where('l.entry_type', 'verified_deposit')->where('l.wallet_type', 'activation_balance')->where('l.status', 'completed')->where('l.reference_type', 'member_deposit_request')->where('d.status', 'approved')->whereRaw("l.transaction_key = 'deposit:credit:' || d.id::text")->whereColumn('l.amount', 'd.amount')->where('l.amount', '>', 0)->sum('l.amount');
        $used = (string) DB::table('financial_ledger')->where('user_id', $member->id)->where('wallet_type', 'activation_balance')->where('status', 'completed')->where('amount', '<', 0)->sum('amount');

        return bccomp(bcsub($verified, $used, 8), $amount, 8) >= 0;
    }
}
