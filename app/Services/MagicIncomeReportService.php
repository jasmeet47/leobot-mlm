<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class MagicIncomeReportService
{
    /** @return array{weekly_usdt: string, monthly_usdt: string, lifetime_usdt: string} */
    public static function summary(int $memberId): array
    {
        $totals = DB::table('magic_income_entitlements')
            ->where('beneficiary_user_id', $memberId)->where('status', 'credited')
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN paid_amount ELSE 0 END), 0) AS weekly_usdt,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN paid_amount ELSE 0 END), 0) AS monthly_usdt,
                COALESCE(SUM(paid_amount), 0) AS lifetime_usdt', [now()->startOfWeek(), now()->startOfMonth()])->first();

        return ['weekly_usdt' => (string) $totals->weekly_usdt, 'monthly_usdt' => (string) $totals->monthly_usdt,
            'lifetime_usdt' => (string) $totals->lifetime_usdt];
    }
}
