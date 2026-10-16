<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBlockedAccessSafetyTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private leobotOriginalRefreshDatabase;
    }

    public function refreshDatabase(): void
    {
        $name = config('database.default');
        $cfg = config('database.connections.' . $name, []);

        $safe = app()->environment('testing')
            && $name === 'pgsql'
            && ($cfg['host'] ?? '') === '127.0.0.1'
            && (string) ($cfg['port'] ?? '') === '5433'
            && ($cfg['database'] ?? '') === 'leobot_financial_disposable'
            && empty($cfg['url']);

        if (!$safe) {
            throw new \RuntimeException(
                'SAFETY STOP: Disposable PostgreSQL required.'
            );
        }

        $identity = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT current_database() AS database_name, inet_server_port() AS server_port'
        );

        if (!$identity
            || $identity->database_name !== 'leobot_financial_disposable'
            || (int) $identity->server_port !== 5433) {
            throw new \RuntimeException(
                'SAFETY STOP: Database identity mismatch.'
            );
        }

        $this->leobotOriginalRefreshDatabase();
    }

    public function test_blocked_admin_and_super_admin_cannot_access_admin_dashboard(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $id = DB::table('users')->insertGetId([
                'username' => 'BLOCK' . Str::upper(Str::random(12)),
                'name' => 'Blocked Administrator',
                'email' => Str::uuid() . '@example.test',
                'phone' => '9000000000',
                'password' => Hash::make('TestPassword123!'),
                'security_pin' => null,
                'status' => 'active',
                'role' => $role,
                'is_blocked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs(User::findOrFail($id))
                ->get('/admin/dashboard')
                ->assertForbidden();
        }
    }
}
