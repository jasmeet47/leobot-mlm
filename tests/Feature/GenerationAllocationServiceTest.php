<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GenerationAllocationService;
use App\Services\TradingProfitPoolService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class GenerationAllocationServiceTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        $safe =
            app()->environment('testing') &&
            $connection->getDriverName() === 'pgsql' &&
            ($config['host'] ?? '') === '127.0.0.1' &&
            (string) ($config['port'] ?? '') === '5433' &&
            ($config['database'] ?? '') === 'leobot_financial_disposable' &&
            empty($config['url']);

        if ($safe) {
            $identity = DB::selectOne(
                'SELECT current_database() AS database_name, inet_server_port() AS server_port'
            );

            $safe =
                $identity->database_name === 'leobot_financial_disposable' &&
                (int) $identity->server_port === 5433;
        }

        if (! $safe) {
            throw new RuntimeException(
                'SAFETY STOP: Financial tests require local PostgreSQL 127.0.0.1:5433/leobot_financial_disposable.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        \Tests\Support\FinancialSeedState::captureIfNeeded();
    }

    protected function afterTruncatingDatabase(): void
    {
        \Tests\Support\FinancialSeedState::restoreOrCapture();
    }

    private function user(
        string $username,
        ?int $parent = null,
        string $status = 'active',
        string $role = 'member'
    ): User {
        $uuid = (string) Str::uuid();

        $id = DB::table('users')->insertGetId([
            'username' => $username,
            'name' => 'Generation Test User',
            'email' => $uuid . '@example.test',
            'phone' => '9000000000',
            'sponsor_user_id' => $parent,
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
        string $status = 'active',
        string $role = 'admin'
    ): User {
        return $this->user($username, null, $status, $role);
    }

    private function package(
        User $user,
        string $amount,
        string $status = 'active'
    ): void {
        DB::table('activation_packages')->insert([
            'user_id' => $user->id,
            'activation_key' => (string) Str::uuid(),
            'investment_amount' => $amount,
            'status' => $status,
            'activated_at' => $status === 'active' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function funded(
        User $admin,
        string $ref = 'GLOBAL-PERIOD-001'
    ): int {
        DB::table('company_wallet')
            ->where('id', 1)
            ->update(['balance' => '2000.00000000']);

        return TradingProfitPoolService::fund(
            $admin,
            '1000.00000000',
            $ref
        );
    }

    private function money(string $expected, mixed $actual): void
    {
        $this->assertSame(
            0,
            bccomp($expected, (string) $actual, 8)
        );
    }

    public function test_no_candidates_remain_unallocated_and_do_not_pay(): void
    {
        $admin = $this->admin();
        $fund = $this->funded($admin);
        $run = GenerationAllocationService::allocate($admin, $fund);

        $this->assertGreaterThan(0, $run);
        $this->money('300', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('unallocated_balance'));
        $this->money('0', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('held_balance'));
        $this->money('0', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('payable_balance'));
        $this->assertSame(
            0,
            DB::table('generation_member_allocations')->count()
        );
        $this->money('0', $admin->fresh()->available_balance);
        $this->assertSame(2, DB::table('financial_ledger')->count());
    }

    public function test_level_one_business_weights_are_sixty_thirty_from_ninety(): void
    {
        $admin = $this->admin();
        $a = $this->user('TXSPONSA');
        $b = $this->user('TXSPONSB');
        $childA = $this->user('TXCHILDA', $a->id);
        $childB = $this->user('TXCHILDB', $b->id);

        $this->package($a, '25');
        $this->package($b, '25');
        $this->package($childA, '600');
        $this->package($childB, '300');

        $fund = $this->funded($admin);
        $run = GenerationAllocationService::allocate($admin, $fund);

        $aRow = DB::table('generation_member_allocations')
            ->where('allocation_run_id', $run)
            ->where('level_number', 1)
            ->where('user_id', $a->id)
            ->first();

        $bRow = DB::table('generation_member_allocations')
            ->where('allocation_run_id', $run)
            ->where('level_number', 1)
            ->where('user_id', $b->id)
            ->first();

        $this->money('600', $aRow->network_business_snapshot);
        $this->money('300', $bRow->network_business_snapshot);
        $this->money('60', $aRow->share_amount);
        $this->money('30', $bRow->share_amount);
        $this->assertSame('payable', $aRow->classification);
        $this->assertSame('payable', $bRow->classification);

        $this->money('90', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('payable_balance'));
        $this->money('210', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('unallocated_balance'));
        $this->money('300', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('balance'));

        $this->money('0', $a->fresh()->available_balance);
        $this->money('0', $b->fresh()->lifetime_income);
        $this->assertSame(4, DB::table('financial_ledger')->count());
    }

    public function test_unqualified_share_is_held_not_redistributed(): void
    {
        $admin = $this->admin();
        $a = $this->user('TXSPONSA');
        $b = $this->user('TXSPONSB');
        $childA = $this->user('TXCHILDA', $a->id);
        $childB = $this->user('TXCHILDB', $b->id);

        $this->package($a, '25');
        $this->package($childA, '600');
        $this->package($childB, '300');

        $fund = $this->funded($admin);
        $run = GenerationAllocationService::allocate($admin, $fund);

        $bRow = DB::table('generation_member_allocations')
            ->where('allocation_run_id', $run)
            ->where('user_id', $b->id)
            ->first();

        $this->assertSame('held', $bRow->classification);
        $this->assertSame(
            'no_active_package',
            $bRow->qualification_reason
        );
        $this->money('30', $bRow->share_amount);

        $this->money('30', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('held_balance'));
        $this->money('60', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('payable_balance'));
        $this->money('210', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('unallocated_balance'));
        $this->assertSame(6, DB::table('financial_ledger')->count());
    }

    public function test_inactive_candidate_is_held(): void
    {
        $admin = $this->admin();
        $inactive = $this->user('TXINACTIVE', null, 'inactive');
        $child = $this->user('TXCHILD', $inactive->id);

        $this->package($inactive, '10');
        $this->package($child, '600');

        $fund = $this->funded($admin);
        $run = GenerationAllocationService::allocate($admin, $fund);

        $row = DB::table('generation_member_allocations')
            ->where('allocation_run_id', $run)
            ->first();

        $this->assertSame(
            'member_inactive',
            $row->qualification_reason
        );
        $this->money('90', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('held_balance'));
    }

    public function test_level_two_requires_five_hundred_level_one_business(): void
    {
        $admin = $this->admin();
        $root = $this->user('TXROOT');
        $mid = $this->user('TXMID', $root->id);
        $child = $this->user('TXBOTTOM', $mid->id);

        $this->package($root, '10');
        $this->package($mid, '100');
        $this->package($child, '500');

        $fund = $this->funded($admin);
        $run = GenerationAllocationService::allocate($admin, $fund);

        $row = DB::table('generation_member_allocations')
            ->where('allocation_run_id', $run)
            ->where('level_number', 2)
            ->where('user_id', $root->id)
            ->first();

        $this->assertSame('level_locked', $row->qualification_reason);
        $this->money('15', $row->share_amount);
        $this->money('15', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('held_balance'));
    }

    public function test_retry_does_not_change_snapshot_or_double_count(): void
    {
        $admin = $this->admin();
        $parent = $this->user('TXPARENT');
        $child = $this->user('TXCHILD', $parent->id);

        $this->package($parent, '10');
        $this->package($child, '500');

        $fund = $this->funded($admin);
        $first = GenerationAllocationService::allocate($admin, $fund);

        DB::table('users')
            ->where('id', $parent->id)
            ->update(['status' => 'inactive']);

        $again = GenerationAllocationService::allocate($admin, $fund);

        $this->assertSame($first, $again);
        $this->assertSame(
            1,
            DB::table('generation_allocation_runs')->count()
        );
        $this->money('90', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('payable_balance'));
        $this->assertSame(4, DB::table('financial_ledger')->count());
    }

    public function test_non_admin_cannot_classify(): void
    {
        $admin = $this->admin();
        $nonAdmin = $this->user('TXMEMBER');
        $fund = $this->funded($admin);

        $this->expectException(AuthorizationException::class);

        GenerationAllocationService::allocate($nonAdmin, $fund);
    }

    public function test_disabled_generation_setting_is_required(): void
    {
        $admin = $this->admin();
        $fund = $this->funded($admin);

        DB::table('generation_pool_settings')
            ->where('id', 1)
            ->update(['generation_enabled' => true]);

        $this->expectException(RuntimeException::class);

        GenerationAllocationService::allocate($admin, $fund);
    }

    public function test_corrupt_pool_fails_without_any_allocation(): void
    {
        $admin = $this->admin();
        $fund = $this->funded($admin);

        DB::table('generation_pool_accounts')
            ->where('id', 1)
            ->update(['unallocated_balance' => '299.00000000']);

        $this->expectException(RuntimeException::class);

        GenerationAllocationService::allocate($admin, $fund);
    }

    public function test_invalid_funding_id_is_rejected(): void
    {
        $admin = $this->admin();

        $this->expectException(\InvalidArgumentException::class);

        GenerationAllocationService::allocate($admin, 0);
    }

    public function test_new_profit_period_can_fund_after_previous_allocation(): void
    {
        $admin = $this->admin();
        $parent = $this->user('TXPARENT');
        $child = $this->user('TXCHILD', $parent->id);

        $this->package($parent, '10');
        $this->package($child, '500');

        $fund = $this->funded($admin);
        GenerationAllocationService::allocate($admin, $fund);

        $second = TradingProfitPoolService::fund(
            $admin,
            '1000',
            'SECOND-PERIOD'
        );

        $this->assertNotEquals($fund, $second);
        $this->assertSame(
            102,
            DB::table('generation_level_budgets')->count()
        );
        $this->money('600', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('balance'));
        $this->money('90', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('payable_balance'));
        $this->money('510', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('unallocated_balance'));
    }

    public function test_inactive_level_retains_its_budget_unallocated(): void
    {
        $admin = $this->admin();

        DB::table('level_settings')
            ->where('level_number', 1)
            ->update(['is_active' => false]);

        $parent = $this->user('TXPARENT');
        $child = $this->user('TXCHILD', $parent->id);

        $this->package($parent, '10');
        $this->package($child, '500');

        $fund = $this->funded($admin);
        GenerationAllocationService::allocate($admin, $fund);

        $this->money('90', DB::table('generation_level_budgets')
            ->where('funding_id', $fund)
            ->where('level_number', 1)
            ->value('unallocated_amount'));

        $this->assertSame(
            0,
            DB::table('generation_member_allocations')->count()
        );
    }

    public function test_allocation_and_financial_ledger_are_atomic(): void
    {
        $admin = $this->admin();
        $fund = $this->funded($admin);

        DB::table('generation_level_budgets')
            ->where('funding_id', $fund)
            ->where('level_number', 2)
            ->update(['held_amount' => '1.00000000']);

        try {
            GenerationAllocationService::allocate($admin, $fund);
            $this->fail('Corrupt level budget must reject allocation.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Budget', $e->getMessage());
        }

        $this->assertSame(
            0,
            DB::table('generation_allocation_runs')->count()
        );
        $this->assertSame(
            0,
            DB::table('generation_member_allocations')->count()
        );
        $this->assertSame(2, DB::table('financial_ledger')->count());
        $this->money('300', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('unallocated_balance'));
    }

    // ROLE-BASED SECURITY TESTS

    public function test_username_admin_without_role_cannot_allocate(): void
    {
        $admin = $this->admin();
        $fund = $this->funded($admin);

        $member = $this->user('ADMIN-LOOKALIKE');

        DB::table('users')
            ->where('id', $admin->id)
            ->update(['role' => 'member']);

        $this->expectException(AuthorizationException::class);

        GenerationAllocationService::allocate($admin, $fund);
    }

    public function test_admin_with_different_username_can_allocate(): void
    {
        $admin = $this->admin('FINANCEADMIN');
        $fund = $this->funded($admin);

        $run = GenerationAllocationService::allocate($admin, $fund);

        $this->assertGreaterThan(0, $run);
        $this->assertSame(
            1,
            DB::table('generation_allocation_runs')->count()
        );
    }

    public function test_super_admin_can_allocate(): void
    {
        $admin = $this->admin('SUPERUSER', 'active', 'super_admin');
        $fund = $this->funded($admin);

        $run = GenerationAllocationService::allocate($admin, $fund);

        $this->assertGreaterThan(0, $run);
    }

    public function test_inactive_admin_cannot_allocate(): void
    {
        $admin = $this->admin();
        $fund = $this->funded($admin);

        DB::table('users')
            ->where('id', $admin->id)
            ->update(['status' => 'inactive']);

        $this->expectException(AuthorizationException::class);

        GenerationAllocationService::allocate($admin, $fund);
    }
}
