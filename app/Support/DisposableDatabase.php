<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DisposableDatabase
{
    public static function assertSafe(): void
    {
        $connection = DB::connection();
        $config = $connection->getConfig();
        if (! app()->environment('testing') || $connection->getDriverName() !== 'pgsql'
            || ($config['host'] ?? '') !== '127.0.0.1'
            || (string) ($config['port'] ?? '') !== '5433'
            || ($config['database'] ?? '') !== 'leobot_financial_disposable'
            || ! empty($config['url'])) {
            throw new RuntimeException('SAFETY STOP: Only the isolated disposable PostgreSQL database is permitted.');
        }
        $identity = $connection->selectOne('SELECT current_database() AS db, inet_server_port() AS port');
        if (! $identity || $identity->db !== 'leobot_financial_disposable' || (int) $identity->port !== 5433) {
            throw new RuntimeException('SAFETY STOP: Physical disposable database identity mismatch.');
        }
    }
}
