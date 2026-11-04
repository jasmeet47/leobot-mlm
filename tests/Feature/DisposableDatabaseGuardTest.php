<?php

namespace Tests\Feature;

use App\Support\DisposableDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DisposableDatabaseGuardTest extends TestCase
{
    public function test_guard_rejects_development_database_before_connecting(): void
    {
        $original = config('database.connections.pgsql.database');
        try {
            config(['database.connections.pgsql.database' => 'leobot_dev']);
            DB::purge('pgsql');
            $this->expectExceptionMessage('SAFETY STOP');
            DisposableDatabase::assertSafe();
        } finally {
            config(['database.connections.pgsql.database' => $original]);
            DB::purge('pgsql');
        }
    }
}
