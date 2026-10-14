<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

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

    private static function assertDisposableDatabase(): void
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        $safe = app()->environment('testing')
            && $connection->getDriverName() === 'pgsql'
            && ($config['host'] ?? '') === '127.0.0.1'
            && (string) ($config['port'] ?? '') === '5433'
            && ($config['database'] ?? '') === 'leobot_financial_disposable'
            && empty($config['url']);

        if ($safe) {
            $identity = DB::selectOne(
                'SELECT current_database() AS database_name, inet_server_port() AS server_port'
            );

            $safe = $identity
                && $identity->database_name === 'leobot_financial_disposable'
                && (int) $identity->server_port === 5433;
        }

        if (! $safe) {
            throw new RuntimeException(
                'SAFETY STOP: Financial baseline requires disposable local PostgreSQL.'
            );
        }
    }

    public static function captureIfNeeded(): void
    {
        if (self::$baseline === null) {
            self::restoreOrCapture();
        }
    }

    public static function restoreOrCapture(): void
    {
        self::assertDisposableDatabase();

        if (self::$baseline === null) {
            $candidate = [];

            foreach (self::TABLES as $table) {
                $rows = DB::table($table)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();

                if ($rows === []) {
                    throw new RuntimeException(
                        "SAFETY STOP: Missing financial baseline rows in {$table}."
                    );
                }

                $candidate[$table] = $rows;
            }

            $levels = array_map(
                fn ($row) => (int) $row['level_number'],
                $candidate['level_settings']
            );

            sort($levels);

            if ($levels !== range(1, 51)) {
                throw new RuntimeException(
                    'SAFETY STOP: Financial baseline must contain exactly 51 levels.'
                );
            }

            self::$baseline = $candidate;

            return;
        }

        foreach (self::$baseline as $table => $rows) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException(
                    "SAFETY STOP: Refusing to restore into nonempty {$table}."
                );
            }

            DB::table($table)->insert($rows);
        }
    }
}