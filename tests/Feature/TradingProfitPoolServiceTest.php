<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TradingProfitPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression guard for the retired standalone generation funding endpoint.
 * Positive 67/30/3 funding, authorization, idempotency and ledger checks
 * remain in UnifiedProfitSharingServiceTest. Never revive this endpoint.
 */
class TradingProfitPoolServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions {
        beginDatabaseTransaction as private beginSafeTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        $connection = \Illuminate\Support\Facades\DB::connection();
        $config = $connection->getConfig();

        $safe = app()->environment('testing')
            && $connection->getDriverName() === 'pgsql'
            && ($config['host'] ?? '') === '127.0.0.1'
            && (string) ($config['port'] ?? '') === '5433'
            && ($config['database'] ?? '') === 'leobot_financial_disposable'
            && empty($config['url']);

        if ($safe) {
            $identity = \Illuminate\Support\Facades\DB::selectOne(
                'SELECT current_database() AS database_name, inet_server_port() AS server_port'
            );

            $safe = $identity
                && $identity->database_name === 'leobot_financial_disposable'
                && (int) $identity->server_port === 5433;
        }

        if (! $safe) {
            throw new \RuntimeException(
                'SAFETY STOP: Disposable PostgreSQL required.'
            );
        }

        $this->beginSafeTransaction();
    }

    public static function retiredCases(): array
    {
        return [
            'active admin standard' => ['admin', 'active', '1000', 'PERIOD-001', false, false, false],
            'active admin mixed case' => ['admin', 'active', '1000.00000000', 'period-001', false, false, false],
            'same ref different amount' => ['admin', 'active', '900', 'PERIOD-002', false, false, false],
            'insufficient company wallet' => ['admin', 'active', '1000', 'PERIOD-003', false, false, false],
            'active member' => ['member', 'active', '1000', 'PERIOD-004', false, false, false],
            'inactive admin' => ['admin', 'inactive', '1000', 'PERIOD-005', false, false, false],
            'combined setting on' => ['admin', 'active', '1000', 'PERIOD-006', true, false, false],
            'generation payout setting on' => ['admin', 'active', '1000', 'PERIOD-007', false, true, false],
            'magic payout setting on' => ['admin', 'active', '1000', 'PERIOD-008', false, false, true],
            'tiny profit' => ['admin', 'active', '0.00000001', 'PERIOD-009', false, false, false],
            'zero profit' => ['admin', 'active', '0', 'PERIOD-010', false, false, false],
            'negative profit' => ['admin', 'active', '-1000', 'PERIOD-011', false, false, false],
            'another profit period' => ['admin', 'active', '500', 'PERIOD-012', false, false, false],
            'username ADMIN with member role' => ['member', 'active', '1000', 'ROLE-DENIED', false, false, false],
            'different admin username' => ['admin', 'active', '1000', 'ROLE-ADMIN-ALLOWED', false, false, false],
            'super admin' => ['super_admin', 'active', '1000', 'ROLE-SUPER-ALLOWED', false, false, false],
            'revoked admin' => ['member', 'inactive', '1000', 'ROLE-REVOKED', false, false, false],
            'magic reference collision' => ['admin', 'active', '1000', 'SHARED-PROFIT-REF-001', false, false, false],
        ];
    }

    #[DataProvider('retiredCases')]
    public function test_retired_generation_only_funding_is_blocked_without_financial_writes(
        string $role,
        string $status,
        string $amount,
        string $reference,
        bool $combined,
        bool $generation,
        bool $magic
    ): void {
        $id = DB::table('users')->insertGetId([
            'username' => 'RETIRE-TEST',
            'name' => 'Retired Funding Test',
            'email' => 'retired-funding@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => $status,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = User::findOrFail($id);

        DB::table('company_wallet')->where('id', 1)
            ->update(['balance' => '1000.00000000']);
        DB::table('profit_sharing_settings')->where('id', 1)
            ->update(['combined_funding_enabled' => $combined]);
        DB::table('generation_pool_settings')->where('id', 1)
            ->update(['generation_enabled' => $generation]);
        DB::table('magic_income_settings')->where('id', 1)
            ->update(['magic_enabled' => $magic]);

        $before = [
            'company' => (string) DB::table('company_wallet')->where('id', 1)->value('balance'),
            'generation' => (string) DB::table('generation_pool_accounts')->where('id', 1)->value('balance'),
            'self' => (string) DB::table('profit_sharing_reserve_accounts')->where('id', 1)->value('self_balance'),
            'magic' => (string) DB::table('profit_sharing_reserve_accounts')->where('id', 1)->value('magic_balance'),
            'ledger' => DB::table('financial_ledger')->count(),
            'generation_fundings' => DB::table('generation_profit_fundings')->count(),
            'unified_fundings' => DB::table('profit_sharing_fundings')->count(),
            'budgets' => DB::table('generation_level_budgets')->count(),
        ];

        try {
            TradingProfitPoolService::fund($admin, $amount, $reference);
            $this->fail('Retired generation-only funding must never succeed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Legacy generation-only funding has been permanently retired.',
                $exception->getMessage()
            );
        }

        $this->assertSame($before['company'],
            (string) DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->assertSame($before['generation'],
            (string) DB::table('generation_pool_accounts')->where('id', 1)->value('balance'));
        $this->assertSame($before['self'],
            (string) DB::table('profit_sharing_reserve_accounts')->where('id', 1)->value('self_balance'));
        $this->assertSame($before['magic'],
            (string) DB::table('profit_sharing_reserve_accounts')->where('id', 1)->value('magic_balance'));
        $this->assertSame($before['ledger'], DB::table('financial_ledger')->count());
        $this->assertSame($before['generation_fundings'],
            DB::table('generation_profit_fundings')->count());
        $this->assertSame($before['unified_fundings'],
            DB::table('profit_sharing_fundings')->count());
        $this->assertSame($before['budgets'],
            DB::table('generation_level_budgets')->count());
    }
}
