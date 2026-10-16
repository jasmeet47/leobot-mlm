<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRoleSecurityTest extends TestCase
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

    private function createUser(
        string $username,
        string $role = 'member',
        string $status = 'active'
    ): User {
        $id = DB::table('users')->insertGetId([
            'username' => $username,
            'name' => 'Role Security Test',
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

    /**
     * Set test-only admin environment values.
     * Restore original values after each test.
     */
    private function withAdminSeederEnvironment(
        string $username,
        callable $callback
    ): void {
        $values = [
            'ADMIN_USERNAME' => $username,
            'ADMIN_PASSWORD' => 'SeederTestPassword123!',
        ];

        $original = [];

        foreach ($values as $key => $value) {
            $original[$key] = [
                'env_exists' => array_key_exists($key, $_ENV),
                'env_value' => $_ENV[$key] ?? null,
                'server_exists' => array_key_exists($key, $_SERVER),
                'server_value' => $_SERVER[$key] ?? null,
            ];

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            $callback();
        } finally {
            foreach ($original as $key => $old) {
                if ($old['env_exists']) {
                    $_ENV[$key] = $old['env_value'];
                } else {
                    unset($_ENV[$key]);
                }

                if ($old['server_exists']) {
                    $_SERVER[$key] = $old['server_value'];
                } else {
                    unset($_SERVER[$key]);
                }
            }
        }
    }

    /**
     * TEST 1
     * Role column exists and defaults to member.
     */
    public function test_role_column_exists_and_defaults_to_member(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'role')
        );

        $id = DB::table('users')->insertGetId([
            'username' => 'TX123456',
            'name' => 'Default Role Test',
            'email' => Str::uuid() . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            'member',
            DB::table('users')
                ->where('id', $id)
                ->value('role')
        );
    }

    /**
     * TEST 2
     * Normal member cannot access admin settings.
     */
    public function test_normal_member_cannot_access_admin_settings(): void
    {
        $member = $this->createUser('TX123456');

        $this->actingAs($member)
            ->get('/admin/level-config')
            ->assertForbidden();
    }

    /**
     * TEST 3
     * Username ADMIN alone must not grant admin access.
     */
    public function test_admin_username_without_admin_role_is_denied(): void
    {
        $member = $this->createUser('ADMIN', 'member');

        $this->actingAs($member)
            ->get('/admin/level-config')
            ->assertForbidden();
    }

    /**
     * TEST 4
     * Active admin can access admin settings.
     */
    public function test_active_admin_role_can_access_admin_settings(): void
    {
        $admin = $this->createUser('TX123457', 'admin');

        $this->actingAs($admin)
            ->get('/admin/level-config')
            ->assertOk();
    }

    /**
     * TEST 5
     * Active super_admin can access admin settings.
     */
    public function test_active_super_admin_can_access_admin_settings(): void
    {
        $admin = $this->createUser(
            'TX123458',
            'super_admin'
        );

        $this->actingAs($admin)
            ->get('/admin/level-config')
            ->assertOk();
    }

    /**
     * TEST 6
     * Inactive admin must be denied.
     */
    public function test_inactive_admin_role_is_denied(): void
    {
        $admin = $this->createUser(
            'TX123459',
            'admin',
            'inactive'
        );

        $this->actingAs($admin)
            ->get('/admin/level-config')
            ->assertForbidden();
    }

    /**
     * TEST 7
     * Inactive super_admin must be denied.
     */
    public function test_inactive_super_admin_is_denied(): void
    {
        $admin = $this->createUser(
            'TX123460',
            'super_admin',
            'inactive'
        );

        $this->actingAs($admin)
            ->get('/admin/level-config')
            ->assertForbidden();
    }

    /**
     * TEST 8
     * Admin login redirects to admin settings.
     */
    public function test_admin_role_login_redirects_to_admin_settings(): void
    {
        $admin = $this->createUser(
            'TX123461',
            'admin'
        );

        $this->post('/login', [
            'username' => $admin->username,
            'password' => 'TestPassword123!',
        ])->assertRedirect(route('admin.dashboard'));
    }

    /**
     * TEST 9
     * Super admin login redirects to admin settings.
     */
    public function test_super_admin_login_redirects_to_admin_settings(): void
    {
        $admin = $this->createUser(
            'TX123462',
            'super_admin'
        );

        $this->post('/login', [
            'username' => $admin->username,
            'password' => 'TestPassword123!',
        ])->assertRedirect(route('admin.dashboard'));
    }

    /**
     * TEST 10
     * ADMIN username without role must not get admin redirect.
     */
    public function test_admin_username_without_role_does_not_get_admin_redirect(): void
    {
        $member = $this->createUser(
            'ADMIN',
            'member'
        );

        $this->post('/login', [
            'username' => $member->username,
            'password' => 'TestPassword123!',
        ])->assertRedirect(route('security-pin.form'));
    }

    /**
     * TEST 11 - NEW
     *
     * Newly seeded ADMIN must receive admin role.
     *
     * Expected RED before fixing DatabaseSeeder.
     */
    public function test_new_admin_seeder_assigns_admin_role(): void
    {
        $this->withAdminSeederEnvironment(
            'ADMIN_SEEDER_NEW',
            function (): void {
                (new DatabaseSeeder())->run();

                $admin = User::where(
                    'username',
                    'ADMIN_SEEDER_NEW'
                )->firstOrFail();

                $this->assertSame(
                    'active',
                    $admin->status
                );

                $this->assertSame(
                    'admin',
                    $admin->role
                );
            }
        );
    }

    /**
     * TEST 12 - NEW
     *
     * Existing member must not be promoted automatically.
     * Password and security PIN must remain unchanged.
     */
    public function test_existing_member_is_not_promoted_by_admin_seeder(): void
    {
        $existing = $this->createUser(
            'ADMIN_SEEDER_EXISTING',
            'member'
        );

        DB::table('users')
            ->where('id', $existing->id)
            ->update([
                'security_pin' => 'KEEP-EXISTING-PIN',
            ]);

        $originalPassword = $existing->password;

        $this->withAdminSeederEnvironment(
            'ADMIN_SEEDER_EXISTING',
            function () use (
                $existing,
                $originalPassword
            ): void {
                (new DatabaseSeeder())->run();

                $account = $existing->fresh();

                $this->assertSame(
                    'member',
                    $account->role
                );

                $this->assertSame(
                    $originalPassword,
                    $account->password
                );

                $this->assertSame(
                    'KEEP-EXISTING-PIN',
                    $account->security_pin
                );

                $this->assertSame(
                    1,
                    User::where(
                        'username',
                        'ADMIN_SEEDER_EXISTING'
                    )->count()
                );
            }
        );
    }
}