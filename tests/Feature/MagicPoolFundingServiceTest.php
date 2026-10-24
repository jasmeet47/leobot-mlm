<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MagicPoolFundingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Run ONLY with phpunit.postgres.xml against isolated lumiflowon_final_regression_20261024.
 * DatabaseTruncation is destructive to its selected database.
 */
class MagicPoolFundingServiceTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshDatabaseWithLaravelTrait;
    }

    public function refreshDatabase()
    {
        // Validate the actual database before Laravel may run migrate:fresh.
        // The existing guard permits only the disposable PostgreSQL database.
        $this->beforeTruncatingDatabase();

        $this->refreshDatabaseWithLaravelTrait();
    }
    protected function beforeTruncatingDatabase(): void
    {
        $connection = DB::connection();
        $settings = $connection->getConfig();

        $safe = app()->environment('testing')
            && $connection->getDriverName() === 'pgsql'
            && ($settings['host'] ?? '') === '127.0.0.1'
            && (string) ($settings['port'] ?? '') === '5433'
            && ($settings['database'] ?? '') === 'lumiflowon_final_regression_20261024'
            && empty($settings['url']);

        if ($safe) {
            $identity = DB::selectOne(
                'SELECT current_database() AS database_name, inet_server_port() AS server_port'
            );

            $safe = $identity
                && $identity->database_name === 'lumiflowon_final_regression_20261024'
                && (int) $identity->server_port === 5433;
        }

        if (! $safe) {
            throw new RuntimeException(
                'SAFETY STOP: Financial tests require local PostgreSQL 127.0.0.1:5433/lumiflowon_final_regression_20261024.'
            );
        }
        \Tests\Support\FinancialSeedState::captureIfNeeded();
    }

    protected function setUp(): void
    {
        parent::setUp();

        \Tests\Support\FinancialSeedState::captureIfNeeded();

        config()->set('leobot.magic_pool_funding_enabled', false);
    }

    protected function afterTruncatingDatabase(): void
    {
        \Tests\Support\FinancialSeedState::restoreOrCapture();
    }

    private function user(
        string $username,
        string $role = 'member',
        string $status = 'active'
    ): User {
        $id = DB::table('users')->insertGetId([
            'username' => $username,
            'name' => 'Test User',
            'email' => str_replace('-', '', (string) Str::uuid()) . '@example.test',
            'phone' => (string) random_int(1000000000, 9999999999),
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => $status,
            'role' => $role,
            'activation_balance' => '0.00000000',
            'available_balance' => '0.00000000',
            'total_investment' => '0.00000000',
            'lifetime_income' => '0.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function admin(
        string $username = 'ADMIN',
        string $role = 'admin',
        string $status = 'active'
    ): User {
        return $this->user($username, $role, $status);
    }

    private function enable(
        string $companyBalance = '1000.00000000'
    ): void {
        config()->set('leobot.magic_pool_funding_enabled', true);

        DB::table('company_wallet')
            ->where('id', 1)
            ->update(['balance' => $companyBalance]);
    }

    private function assertAmount(
        string $expected,
        mixed $actual
    ): void {
        $this->assertSame(
            0,
            bccomp($expected, (string) $actual, 8),
            "Expected {$expected}, got {$actual}"
        );
    }

    public function test_both_standalone_paths_are_permanently_rejected_even_when_legacy_gate_is_on(): void
    {
        $admin = $this->admin();
        $this->enable('1000.00000000');
        foreach (['direct', 'trading'] as $mode) {
            try {
                if ($mode === 'direct') {
                    MagicPoolFundingService::fundDirectPool($admin, '10', 'NO-STANDALONE-01');
                } else {
                    MagicPoolFundingService::fundFromTradingProfit($admin, '100', 'NO-STANDALONE-02');
                }
                $this->fail('Standalone Magic funding must never be enabled.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('disabled', $e->getMessage());
            }
        }
        $this->assertSame(0, DB::table('magic_pool_fundings')->count());
        $this->assertSame(0, DB::table('financial_ledger')->count());
        $this->assertAmount('1000', DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->assertAmount('0', DB::table('profit_sharing_reserve_accounts')->where('id', 1)->value('magic_balance'));
    }

    public function test_standalone_paths_never_bypass_disabled_rule_for_any_role(): void
    {
        foreach (['member', 'admin', 'super_admin'] as $role) {
            $user = $this->user('ROLE' . strtoupper(Str::random(10)), $role);
            config()->set('leobot.magic_pool_funding_enabled', true);
            foreach (['direct', 'trading'] as $mode) {
                try {
                    if ($mode === 'direct') {
                        MagicPoolFundingService::fundDirectPool($user, '10', 'ROLE-TEST-01');
                    } else {
                        MagicPoolFundingService::fundFromTradingProfit($user, '100', 'ROLE-TEST-02');
                    }
                    $this->fail('Standalone path unexpectedly allowed.');
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('disabled', $e->getMessage());
                }
            }
        }
        $this->assertSame(0, DB::table('magic_pool_fundings')->count());
        $this->assertSame(0, DB::table('financial_ledger')->count());
    }
}