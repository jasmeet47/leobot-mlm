<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

final class FinancialSeedState
{
    private static ?array $baseline = null;

    private const TABLES = [
        'generation_qualification_settings',
        'level_settings',
        'financial_settings',
        'company_wallet',
        'roi_settings',
        'magic_income_settings',
        'generation_pool_settings',
        'generation_pool_accounts',
        'profit_sharing_settings',
        'profit_sharing_reserve_accounts',
        'referral_privacy_settings',
    ];

    public static function captureIfNeeded(): void
    {
        if (self::$baseline === null) {
            self::restoreOrCapture();
        }
    }
    public static function restoreOrCapture(): void
    {
        if (self::$baseline === null) {
            self::$baseline = [];

            foreach (self::TABLES as $table) {
                self::$baseline[$table] = DB::table($table)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();
            }

            return;
        }

        foreach (self::$baseline as $table => $rows) {
            if ($rows !== []) {
                DB::table($table)->insert($rows);
            }
        }
    }
}
