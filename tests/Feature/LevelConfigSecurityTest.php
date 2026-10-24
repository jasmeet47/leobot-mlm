<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LevelConfigSecurityTest extends TestCase
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

    private function user(
        string $username,
        string $role = 'member',
        string $status = 'active'
    ): User {
        $id = DB::table('users')->insertGetId([
            'username' => $username,
            'name' => 'Level Security Test User',
            'email' => str_replace('-', '', (string) Str::uuid()).'@example.test',
            'phone' => (string) random_int(1000000000, 9999999999),
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => $status,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    public function test_guest_cannot_view_level_settings(): void
    {
        $this->get(route('admin.levels.index'))
            ->assertRedirect('/join');
    }

    public function test_member_named_admin_cannot_view_level_settings(): void
    {
        $this->actingAs(
            $this->user('ADMIN', 'member')
        );

        $this->get(route('admin.levels.index'))
            ->assertForbidden();
    }

    public function test_member_named_admin_cannot_update_level_settings(): void
    {
        $this->actingAs(
            $this->user('ADMIN', 'member')
        );

        $this->post(
            route('admin.levels.update'),
            [
                'commission' => [],
                'active_levels' => [],
            ]
        )->assertForbidden();

        $this->assertSame(
            0,
            DB::table('level_setting_audits')->count()
        );
    }

    public function test_member_named_admin_cannot_view_level_history(): void
    {
        $this->actingAs(
            $this->user('ADMIN', 'member')
        );

        $this->get(route('admin.levels.history'))
            ->assertForbidden();
    }

    public function test_active_admin_with_other_username_can_view_levels(): void
    {
        $this->actingAs(
            $this->user('MANAGER100', 'admin')
        );

        $this->get(route('admin.levels.index'))
            ->assertOk();
    }

    public function test_active_admin_with_other_username_can_view_history(): void
    {
        $this->actingAs(
            $this->user('MANAGER101', 'admin')
        );

        $this->get(route('admin.levels.history'))
            ->assertOk();
    }

    public function test_active_admin_can_reach_update_validation(): void
    {
        $this->actingAs(
            $this->user('MANAGER102', 'admin')
        );

        $this->from(route('admin.levels.index'))
            ->post(
                route('admin.levels.update'),
                [
                    'commission' => [
                        52 => '10',
                    ],
                    'active_levels' => [],
                ]
            )
            ->assertSessionHasErrors(['commission']);

        $this->assertSame(
            0,
            DB::table('level_setting_audits')->count()
        );
    }

    public function test_active_super_admin_can_view_level_settings(): void
    {
        $this->actingAs(
            $this->user('OWNER100', 'super_admin')
        );

        $this->get(route('admin.levels.index'))
            ->assertOk();
    }

    public function test_active_super_admin_can_view_history(): void
    {
        $this->actingAs(
            $this->user('OWNER101', 'super_admin')
        );

        $this->get(route('admin.levels.history'))
            ->assertOk();
    }

    public function test_inactive_admin_cannot_access_level_settings(): void
    {
        $this->actingAs(
            $this->user('MANAGER103', 'admin', 'inactive')
        );

        $this->get(route('admin.levels.index'))
            ->assertForbidden();

        $this->get(route('admin.levels.history'))
            ->assertForbidden();

        $this->post(
            route('admin.levels.update'),
            [
                'commission' => [],
                'active_levels' => [],
            ]
        )->assertForbidden();
    }
}
