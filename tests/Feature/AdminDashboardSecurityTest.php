<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardSecurityTest extends TestCase
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
            && ($cfg['database'] ?? '') === 'lumiflowon_final_regression_20261024'
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
            || $identity->database_name !== 'lumiflowon_final_regression_20261024'
            || (int) $identity->server_port !== 5433) {
            throw new \RuntimeException(
                'SAFETY STOP: Database identity mismatch.'
            );
        }

        $this->leobotOriginalRefreshDatabase();
    }

    private function createUser(
        string $role,
        string $status = 'active'
    ): User {
        $id = DB::table('users')->insertGetId([
            'username' => 'DASH' . Str::upper(Str::random(10)),
            'name' => 'Dashboard Security Test',
            'email' => Str::uuid() . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => $status,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/join');
    }

    public function test_member_cannot_access_dashboard(): void
    {
        $this->actingAs($this->createUser('member'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_inactive_admin_cannot_access_dashboard(): void
    {
        $this->actingAs($this->createUser('admin', 'inactive'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_inactive_super_admin_cannot_access_dashboard(): void
    {
        $this->actingAs($this->createUser('super_admin', 'inactive'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_active_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->createUser('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee('51-Level Commission')
            ->assertSee('Member Management')
            ->assertSee('View Members');
    }

    public function test_active_super_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->createUser('super_admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Referral Privacy');
    }
}