<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

use Tests\TestCase;

class MemberDashboardIncomeTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginSafeTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        $this->assertDisposableDatabase();
        $this->beginSafeTransaction();
    }

    private function assertDisposableDatabase(): void
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        $safe = app()->environment('testing')
            && $connection->getDriverName() === 'pgsql'
            && ($config['host'] ?? '') === '127.0.0.1'
            && (string) ($config['port'] ?? '') === '5433'
            && ($config['database'] ?? '') === 'lumiflowon_final_regression_20261024'
            && empty($config['url']);

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
                'SAFETY STOP: Dashboard income tests require disposable PostgreSQL 127.0.0.1:5433/lumiflowon_final_regression_20261024.'
            );
        }
    }

    private function user(string $role = 'member'): User
    {
        $unique = Str::lower(Str::random(16));

        $id = DB::table('users')->insertGetId([
            'username' => 'TI' . strtoupper(substr($unique, 0, 10)),
            'name' => 'Income Test ' . $unique,
            'email' => $unique . '@example.test',
            'phone' => (string) random_int(1000000000, 9999999999),
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => Hash::make('123456'),
            'status' => 'active',
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function funding(User $admin): array
    {
        $reference = 'INCOME-TEST-' . Str::uuid();

        $generationId = DB::table('generation_profit_fundings')
            ->insertGetId([
                'profit_reference' => $reference,
                'approved_by' => $admin->id,
                'verified_profit_amount' => '100.00000000',
                'pool_percentage_snapshot' => '30.000000000',
                'pool_amount' => '30.00000000',
                'rounding_unallocated' => '0.00000000',
                'status' => 'funded_unallocated',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $unifiedId = DB::table('profit_sharing_fundings')
            ->insertGetId([
                'profit_reference' => $reference,
                'approved_by' => $admin->id,
                'generation_funding_id' => $generationId,
                'verified_profit_amount' => '100.00000000',
                'self_percent_snapshot' => '67.000000000',
                'generation_percent_snapshot' => '30.000000000',
                'magic_percent_snapshot' => '3.000000000',
                'self_amount' => '67.00000000',
                'generation_amount' => '30.00000000',
                'magic_amount' => '3.00000000',
                'funded_total' => '100.00000000',
                'company_rounding_remainder' => '0.00000000',
                'status' => 'reserved_no_payout',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $runId = DB::table('generation_allocation_runs')
            ->insertGetId([
                'funding_id' => $generationId,
                'approved_by' => $admin->id,
                'policy_version' => 'network_business_v1',
                'status' => 'classified_no_payout',
                'payable_total' => '8.00000000',
                'held_total' => '5.00000000',
                'unallocated_total' => '17.00000000',
                'candidate_rows' => 2,
                'snapshot_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'unified' => $unifiedId,
            'generation' => $generationId,
            'run' => $runId,
        ];
    }

    private function allocation(
        array $funding,
        User $member,
        int $level,
        string $amount,
        string $classification,
        string $status
    ): void {
        $budgetId = DB::table('generation_level_budgets')
            ->insertGetId([
                'funding_id' => $funding['generation'],
                'level_number' => $level,
                'rate_percent_snapshot' => '1.000000000',
                'was_active' => true,
                'budget_amount' => '10.00000000',
                'unallocated_amount' => '0.00000000',
                'held_amount' => $classification === 'held'
                    ? $amount : '0.00000000',
                'payable_amount' => $classification === 'payable'
                    ? $amount : '0.00000000',
                'paid_amount' => '0.00000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('generation_member_allocations')->insert([
            'allocation_run_id' => $funding['run'],
            'funding_id' => $funding['generation'],
            'level_budget_id' => $budgetId,
            'user_id' => $member->id,
            'level_number' => $level,
            'network_business_snapshot' => '100.00000000',
            'share_amount' => $amount,
            'unlocked_through_level_snapshot' => 51,
            'classification' => $classification,
            'qualification_reason' => 'dashboard_test',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ledger(
        ?User $member,
        string $entry,
        string $wallet,
        string $amount,
        string $status = 'completed'
    ): void {
        DB::table('financial_ledger')->insert([
            'user_id' => $member?->id,
            'entry_type' => $entry,
            'wallet_type' => $wallet,
            'amount' => $amount,
            'balance_before' => '0.00000000',
            'balance_after' => $amount,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_cannot_read_income_dashboard(): void
    {
        $this->get('/member/dashboard')
            ->assertRedirect('/join');
    }

    public function test_empty_member_income_is_zero_and_magic_is_not_allocated(): void
    {
        $member = $this->user();

        $this->actingAs($member)
            ->get('/member/dashboard')
            ->assertOk()
            ->assertViewHas('incomeSummary', function ($summary) {
                return bccomp($summary['self_reserved'], '0', 8) === 0
                    && bccomp($summary['generation_qualified'], '0', 8) === 0
                    && bccomp($summary['generation_held'], '0', 8) === 0;
            })
            ->assertSee('Magic Income')
            ->assertSee('Not allocated')
            ->assertSee('No completed wallet transactions recorded.');
    }

    public function test_income_reserves_and_wallet_history_are_member_isolated(): void
    {
        $member = $this->user();
        $other = $this->user();
        $admin = $this->user('admin');

        $funding = $this->funding($admin);

        DB::table('profit_sharing_self_reserves')->insert([
            [
                'funding_id' => $funding['unified'],
                'user_id' => $member->id,
                'member_profit_amount' => '20.00000000',
                'self_share_reserved' => '13.40000000',
                'status' => 'reserved_no_payout',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'funding_id' => $funding['unified'],
                'user_id' => $other->id,
                'member_profit_amount' => '30.00000000',
                'self_share_reserved' => '20.10000000',
                'status' => 'reserved_no_payout',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->allocation(
            $funding, $member, 1, '8.00000000',
            'payable', 'qualified_reserved_no_payout'
        );

        $this->allocation(
            $funding, $member, 2, '5.00000000',
            'held', 'held_unqualified'
        );

        $this->ledger(
            $member, 'test_wallet_credit',
            'available_balance', '2.50000000'
        );

        $this->ledger(
            $member, 'test_activation_credit',
            'activation_balance', '3.50000000'
        );

        $this->ledger(
            $other, 'other_member_secret',
            'available_balance', '98765.12345678'
        );

        $this->ledger(
            $member, 'pending_secret',
            'available_balance', '88888.00000000', 'pending'
        );

        $this->ledger(
            null, 'company_reserve_secret',
            'company_reserve', '77777.00000000'
        );

        $this->actingAs($member)
            ->get('/member/dashboard')
            ->assertOk()
            ->assertViewHas('incomeSummary', function ($summary) {
                return bccomp($summary['self_reserved'], '13.4', 8) === 0
                    && bccomp($summary['generation_qualified'], '8', 8) === 0
                    && bccomp($summary['generation_held'], '5', 8) === 0;
            })
            ->assertViewHas('walletHistory', function ($history) {
                $entries = collect($history->items())
                    ->pluck('entry_type')
                    ->all();

                sort($entries);

                return $entries === [
                    'test_activation_credit',
                    'test_wallet_credit',
                ];
            })
            ->assertSee('13.40000000')
            ->assertSee('8.00000000')
            ->assertSee('5.00000000')
            ->assertSee('Not allocated')
            ->assertDontSee('other_member_secret')
            ->assertDontSee('pending_secret')
            ->assertDontSee('company_reserve_secret')
            ->assertDontSee('98765.12345678')
            ->assertDontSee('88888.00000000')
            ->assertDontSee('77777.00000000');
    }
}