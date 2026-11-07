<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class MagicQualificationService
{
    public static function inspect(int $userId): array
    {
        $member = DB::table('users')->where('id', $userId)->first(['role', 'status', 'is_blocked', 'deleted_at']);
        if (! $member || $member->role !== 'member' || ! in_array($member->status, ['active', 'inactive'], true) || $member->is_blocked || $member->deleted_at) {
            return ['eligible' => false, 'qualified_at' => null];
        }
        $dates = [];
        $stake = DB::table('staking_positions as p')->join('financial_ledger as l', function ($j): void {
            $j->on('l.reference_id', '=', 'p.id')->on('l.user_id', '=', 'p.user_id');
        })->where('p.user_id', $userId)->where('p.status', 'active')->whereRaw('p.amount - p.withdrawn_amount >= 50')->where('l.wallet_type', 'staking_principal')->where('l.status', 'completed')->where('l.reference_type', 'staking_position')->whereColumn('l.amount', 'p.amount')->whereRaw("l.transaction_key = 'staking:principal:' || p.request_key")->min('p.created_at');
        if ($stake) {
            $dates[] = $stake;
        }
        $package = DB::table('activation_packages')->where('user_id', $userId)->where('status', 'active')->where('investment_amount', '>=', '50')->min('created_at');
        if ($package) {
            $dates[] = $package;
        }
        $cycle = DB::table('magic_matrix_cycles')->where('user_id', $userId)->where('status', 'active')->where('tier_usdt', '>=', '50')->min('created_at');
        if ($cycle) {
            $dates[] = $cycle;
        }
        sort($dates);

        return ['eligible' => count($dates) > 0, 'qualified_at' => $dates[0] ?? null];
    }
}
