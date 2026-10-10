<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TradingProfitPoolService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TradingProfitPoolServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $username = 'ADMIN', string $status = 'active'): User
    {
        $uuid = str_replace('-', '', (string) Str::uuid());

        $id = DB::table('users')->insertGetId([
            'username' => $username,
            'name' => 'Pool Test User',
            'email' => $uuid . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => $status,
            'activation_balance' => '0.00000000',
            'available_balance' => '0.00000000',
            'total_investment' => '0.00000000',
            'lifetime_income' => '0.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function setCompanyBalance(string $amount): void
    {
        DB::table('company_wallet')->where('id', 1)->update([
            'balance' => $amount,
            'updated_at' => now(),
        ]);
    }

    private function assertMoney(string $expected, mixed $actual): void
    {
        $this->assertSame(0, bccomp($expected, (string) $actual, 8));
    }

    public function test_funding_makes_one_global_pool_and_51_unallocated_budgets(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('500.00000000');

        $id = TradingProfitPoolService::fund(
            $admin,
            '1000.00000000',
            'TRADING-PERIOD-20261010'
        );

        $funding = DB::table('generation_profit_fundings')->find($id);
        $this->assertNotNull($funding);
        $this->assertSame('TRADING-PERIOD-20261010', $funding->profit_reference);
        $this->assertMoney('1000', $funding->verified_profit_amount);
        $this->assertSame(0, bccomp('30', (string) $funding->pool_percentage_snapshot, 9));
        $this->assertMoney('300', $funding->pool_amount);

        $company = DB::table('company_wallet')->find(1);
        $pool = DB::table('generation_pool_accounts')->find(1);
        $this->assertMoney('200', $company->balance);
        $this->assertMoney('300', $pool->balance);
        $this->assertMoney('300', $pool->unallocated_balance);
        $this->assertMoney('300', $pool->total_funded);
        $this->assertMoney('0', $pool->held_balance);
        $this->assertMoney('0', $pool->payable_balance);
        $this->assertMoney('0', $pool->total_paid);

        $budgets = DB::table('generation_level_budgets')
            ->where('funding_id', $id)
            ->orderBy('level_number')
            ->get();

        $this->assertCount(51, $budgets);
        $this->assertSame(1, (int) $budgets[0]->level_number);
        $this->assertMoney('90', $budgets[0]->budget_amount);
        $this->assertMoney('90', $budgets[0]->unallocated_amount);
        $this->assertMoney('0', $budgets[0]->held_amount);
        $this->assertMoney('0', $budgets[0]->paid_amount);
        $this->assertSame(17, (int) $budgets[16]->level_number);
        $this->assertMoney('7.5', $budgets[16]->budget_amount);
        $this->assertMoney('1.5', $budgets[21]->budget_amount); // level 22
        $this->assertMoney('0.75', $budgets[41]->budget_amount); // level 42

        $sum = '0.00000000';
        foreach ($budgets as $budget) {
            $sum = bcadd($sum, (string) $budget->budget_amount, 8);
        }
        $this->assertMoney('300', bcadd($sum, (string) $funding->rounding_unallocated, 8));

        $entries = DB::table('financial_ledger')
            ->where('reference_type', 'generation_profit_funding')
            ->where('reference_id', $id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $entries);
        $this->assertMoney('-300', $entries[0]->amount);
        $this->assertMoney('300', $entries[1]->amount);
        $this->assertSame('company_wallet', $entries[0]->wallet_type);
        $this->assertSame('generation_pool', $entries[1]->wallet_type);
        $this->assertNotEquals($entries[0]->transaction_key, $entries[1]->transaction_key);

        $this->assertMoney('0', $admin->fresh()->available_balance);
        $this->assertMoney('0', $admin->fresh()->lifetime_income);
    }

    public function test_same_profit_reference_is_idempotent_even_after_percentage_changes(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('1000');

        $firstId = TradingProfitPoolService::fund($admin, '1000', 'PERIOD-001');
        DB::table('generation_pool_settings')->where('id', 1)
            ->update(['trading_profit_pool_percent' => '40.000000000']);

        $secondId = TradingProfitPoolService::fund($admin, '1000.00000000', 'period-001');

        $this->assertSame($firstId, $secondId);
        $this->assertSame(1, DB::table('generation_profit_fundings')->count());
        $this->assertSame(51, DB::table('generation_level_budgets')->count());
        $this->assertSame(2, DB::table('financial_ledger')->count());
        $this->assertMoney('700', DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->assertMoney('300', DB::table('generation_pool_accounts')->where('id', 1)->value('balance'));
    }

    public function test_same_profit_reference_with_different_amount_is_rejected(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('1000');
        TradingProfitPoolService::fund($admin, '1000', 'PERIOD-002');

        $this->expectException(RuntimeException::class);
        TradingProfitPoolService::fund($admin, '900', 'PERIOD-002');
    }

    public function test_insufficient_company_funds_rolls_back(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('299.99999999');

        try {
            TradingProfitPoolService::fund($admin, '1000', 'PERIOD-003');
            $this->fail('Funding must reject insufficient company funds.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }

        $this->assertSame(0, DB::table('generation_profit_fundings')->count());
        $this->assertSame(0, DB::table('financial_ledger')->count());
        $this->assertMoney('299.99999999', DB::table('company_wallet')->where('id', 1)->value('balance'));
    }

    public function test_non_admin_cannot_fund(): void
    {
        $member = $this->createUser('TX123456');
        $this->setCompanyBalance('1000');

        $this->expectException(AuthorizationException::class);
        TradingProfitPoolService::fund($member, '1000', 'PERIOD-004');
    }

    public function test_inactive_admin_cannot_fund(): void
    {
        $admin = $this->createUser('ADMIN', 'inactive');
        $this->setCompanyBalance('1000');

        $this->expectException(AuthorizationException::class);
        TradingProfitPoolService::fund($admin, '1000', 'PERIOD-005');
    }

    public function test_invalid_pool_percentage_is_rejected_without_wallet_changes(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('1000');
        DB::table('generation_pool_settings')->where('id', 1)
            ->update(['trading_profit_pool_percent' => '101.000000000']);

        $this->expectException(RuntimeException::class);
        TradingProfitPoolService::fund($admin, '1000', 'PERIOD-006');
    }

    public function test_generation_plus_magic_cannot_exceed_one_hundred_percent(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('1000');
        DB::table('generation_pool_settings')->where('id', 1)
            ->update(['trading_profit_pool_percent' => '98.000000000']);
        DB::table('magic_income_settings')->where('id', 1)
            ->update(['trading_profit_pool_percent' => '3.000000000']);

        try {
            TradingProfitPoolService::fund($admin, '1000', 'PERIOD-COMBINED');
            $this->fail('Combined pool >100% must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('exceed 100%', $e->getMessage());
        }
        $this->assertSame(0, DB::table('generation_profit_fundings')->count());
        $this->assertSame(0, DB::table('financial_ledger')->count());
        $this->assertMoney('1000', DB::table('company_wallet')->where('id', 1)->value('balance'));
    }

    public function test_wrong_level_total_is_rejected_atomically(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('1000');
        DB::table('level_settings')->where('level_number', 1)
            ->update(['commission_percentage' => '31.000000000']);

        try {
            TradingProfitPoolService::fund($admin, '1000', 'PERIOD-007');
            $this->fail('Rates must total 100%.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('100%', $e->getMessage());
        }

        $this->assertMoney('1000', DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->assertSame(0, DB::table('generation_profit_fundings')->count());
        $this->assertSame(0, DB::table('financial_ledger')->count());
    }

    public function test_generation_must_remain_off_in_phase_one(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('1000');
        DB::table('generation_pool_settings')->where('id', 1)
            ->update(['generation_enabled' => true]);

        $this->expectException(RuntimeException::class);
        TradingProfitPoolService::fund($admin, '1000', 'PERIOD-008');
    }

    public function test_tiny_profit_yielding_zero_pool_is_rejected(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('1000');

        $this->expectException(RuntimeException::class);
        TradingProfitPoolService::fund($admin, '0.00000001', 'PERIOD-009');
    }

    public function test_future_held_or_paid_state_is_not_silently_modified(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('1000');
        DB::table('generation_pool_accounts')->where('id', 1)
            ->update(['held_balance' => '1.00000000']);

        $this->expectException(RuntimeException::class);
        TradingProfitPoolService::fund($admin, '1000', 'PERIOD-010');
    }

    public function test_two_profit_periods_fund_one_shared_pool_without_double_counting(): void
    {
        $admin = $this->createUser();
        $this->setCompanyBalance('1000');

        TradingProfitPoolService::fund($admin, '1000', 'PERIOD-011');
        TradingProfitPoolService::fund($admin, '500', 'PERIOD-012');

        $this->assertSame(2, DB::table('generation_profit_fundings')->count());
        $this->assertSame(102, DB::table('generation_level_budgets')->count());
        $this->assertSame(4, DB::table('financial_ledger')->count());
        $this->assertMoney('550', DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->assertMoney('450', DB::table('generation_pool_accounts')->where('id', 1)->value('balance'));
    }
}
