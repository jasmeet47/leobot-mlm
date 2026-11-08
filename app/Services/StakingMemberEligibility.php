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
            $stakingActivation = $wallet === 'activation_balance' && DB::table('member_security_audits')->whereIn('action', ['funded_staking_member_activated', 'staking_member_status_repaired'])->whereRaw("details->>'user_id' = ?", [(string) $member->id])->exists();
            if ($stakingActivation) {
                return bccomp(self::verifiedActivationBalance((int) $member->id), $amount, 8) >= 0;
            }

            return true;
        }
        if ($wallet !== 'activation_balance') {
            return false;
        }

        return bccomp(self::verifiedActivationBalance((int) $member->id), $amount, 8) >= 0;
    }

    public static function verifiedActivationBalance(int $userId): string
    {
        $verified = (string) DB::table('financial_ledger as l')->join('member_deposit_requests as d', function ($join): void {
            $join->on('d.id', '=', 'l.reference_id')->on('d.user_id', '=', 'l.user_id');
        })
            ->where('l.user_id', $userId)->where('l.entry_type', 'verified_deposit')->where('l.wallet_type', 'activation_balance')->where('l.status', 'completed')->where('l.reference_type', 'member_deposit_request')->where('d.status', 'approved')->whereRaw("l.transaction_key = 'deposit:credit:' || d.id::text")->whereColumn('l.amount', 'd.amount')->where('l.amount', '>', 0)->sum('l.amount');
        $used = (string) DB::table('financial_ledger')->where('user_id', $userId)->where('wallet_type', 'activation_balance')->where('status', 'completed')->where('amount', '<', 0)->sum('amount');

        return bccomp(bcadd($verified, $used, 8), '0', 8) > 0 ? bcadd($verified, $used, 8) : '0.00000000';
    }

    public static function canReceiveIncome(object $member): bool
    {
        if ($member->role !== 'member' || ! in_array($member->status, ['active', 'inactive'], true) || $member->is_blocked || $member->deleted_at !== null) {
            return false;
        }
        if ($member->status === 'active') {
            return true;
        }

        return DB::table('staking_positions as p')->join('financial_ledger as l', function ($j): void {
            $j->on('l.reference_id', '=', 'p.id')->on('l.user_id', '=', 'p.user_id');
        })->where('p.user_id', $member->id)->where('l.wallet_type', 'staking_principal')->where('l.reference_type', 'staking_position')->where('l.status', 'completed')->whereColumn('l.amount', 'p.amount')->whereRaw("l.transaction_key = 'staking:principal:' || p.request_key")->exists();
    }
}
