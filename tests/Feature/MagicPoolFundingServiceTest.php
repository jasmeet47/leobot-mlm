<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MagicPoolFundingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Run ONLY with phpunit.postgres.xml against isolated leobot_financial_disposable.
 * DatabaseTruncation is destructive to its selected database.
 */
class MagicPoolFundingServiceTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $connection = DB::connection();
        $settings = $connection->getConfig();

        $safe = app()->environment('testing')
            && $connection->getDriverName() === 'pgsql'
            && ($settings['host'] ?? '') === '127.0.0.1'
            && (string) ($settings['port'] ?? '') === '5433'
            && ($settings['database'] ?? '') === 'leobot_financial_disposable'
            && empty($settings['url']);

        if ($safe) {
            $identity = DB::selectOne(
                'SELECT current_database() AS database_name, inet_server_port() AS server_port'
            );

            $safe = $identity
                && $identity->database_name === 'leobot_financial_disposable'
                && (int) $identity->server_port === 5433;
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
            'phone' => '9000000000',
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

    public function test_funding_is_disabled_by_default(): void
    {
        $admin = $this->admin();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('disabled');

        MagicPoolFundingService::fundDirectPool(
            $admin,
            '10',
            'OFF-001'
        );
    }

    public function test_non_admin_cannot_fund(): void
    {
        $member = $this->user('MEMBER001');

        $this->enable();

        $this->expectException(AuthorizationException::class);

        MagicPoolFundingService::fundDirectPool(
            $member,
            '10',
            'NOADMIN-001'
        );
    }

    public function test_direct_pool_creates_balanced_ledger_and_no_member_payout(): void
    {
        $admin = $this->admin();

        $this->enable();

        $fundingId = MagicPoolFundingService::fundDirectPool(
            $admin,
            '12.50000000',
            'direct-001'
        );

        $funding = DB::table('magic_pool_fundings')
            ->find($fundingId);

        $this->assertSame(
            'DIRECT-001',
            $funding->source_reference
        );

        $this->assertSame(
            'direct_pool',
            $funding->funding_mode
        );

        $this->assertSame(
            'reserved_no_payout',
            $funding->status
        );

        $this->assertNull($funding->verified_profit_amount);
        $this->assertNull($funding->magic_percent_snapshot);

        $this->assertAmount(
            '12.50000000',
            $funding->pool_amount
        );

        $company = DB::table('company_wallet')
            ->where('id', 1)
            ->first();

        $reserve = DB::table('profit_sharing_reserve_accounts')
            ->where('id', 1)
            ->first();

        $this->assertAmount(
            '987.50000000',
            $company->balance
        );

        $this->assertAmount(
            '12.50000000',
            $reserve->magic_balance
        );

        $this->assertAmount(
            '12.50000000',
            $reserve->total_magic_funded
        );

        $ledger = DB::table('financial_ledger')
            ->where('reference_type', 'magic_pool_funding')
            ->where('reference_id', $fundingId)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $ledger);

        $this->assertSame(
            'magic_pool_company_out',
            $ledger[0]->entry_type
        );

        $this->assertSame(
            'magic_pool_reserve_in',
            $ledger[1]->entry_type
        );

        $this->assertAmount(
            '-12.50000000',
            $ledger[0]->amount
        );

        $this->assertAmount(
            '12.50000000',
            $ledger[1]->amount
        );

        $this->assertNotNull($ledger[0]->transaction_key);
        $this->assertNotNull($ledger[1]->transaction_key);

        $this->assertNotSame(
            $ledger[0]->transaction_key,
            $ledger[1]->transaction_key
        );

        $this->assertSame(
            0,
            DB::table('financial_ledger')
                ->where('reference_type', 'magic_pool_funding')
                ->whereNotNull('user_id')
                ->count()
        );
    }

    public function test_trading_profit_uses_admin_rate_snapshot(): void
    {
        $admin = $this->admin();

        $this->enable();

        DB::table('magic_income_settings')
            ->where('id', 1)
            ->update([
                'trading_profit_pool_percent' => '5.000000000'
            ]);

        $fundingId = MagicPoolFundingService::fundFromTradingProfit(
            $admin,
            '200.00000000',
            'profit-001'
        );

        $funding = DB::table('magic_pool_fundings')
            ->find($fundingId);

        $this->assertSame(
            'trading_profit',
            $funding->funding_mode
        );

        $this->assertAmount(
            '200',
            $funding->verified_profit_amount
        );

        $this->assertSame(
            0,
            bccomp(
                '5',
                (string) $funding->magic_percent_snapshot,
                9
            )
        );

        $this->assertAmount(
            '10',
            $funding->pool_amount
        );

        $this->assertAmount(
            '990',
            DB::table('company_wallet')
                ->where('id', 1)
                ->value('balance')
        );
    }

    public function test_identical_retry_is_idempotent_even_after_rate_change(): void
    {
        $admin = $this->admin();

        $this->enable();

        $first = MagicPoolFundingService::fundFromTradingProfit(
            $admin,
            '100',
            'retry-001'
        );

        DB::table('magic_income_settings')
            ->where('id', 1)
            ->update([
                'trading_profit_pool_percent' => '10.000000000'
            ]);

        $second = MagicPoolFundingService::fundFromTradingProfit(
            $admin,
            '100',
            'RETRY-001'
        );

        $this->assertSame($first, $second);

        $this->assertSame(
            1,
            DB::table('magic_pool_fundings')->count()
        );

        $this->assertSame(
            2,
            DB::table('financial_ledger')
                ->where('reference_type', 'magic_pool_funding')
                ->count()
        );

        $this->assertAmount(
            '997',
            DB::table('company_wallet')
                ->where('id', 1)
                ->value('balance')
        );
    }

    public function test_reusing_reference_with_different_amount_is_rejected(): void
    {
        $admin = $this->admin();

        $this->enable();

        MagicPoolFundingService::fundDirectPool(
            $admin,
            '10',
            'same-001'
        );

        try {
            MagicPoolFundingService::fundDirectPool(
                $admin,
                '11',
                'SAME-001'
            );

            $this->fail(
                'Expected duplicate reference rejection.'
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'different amount',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            1,
            DB::table('magic_pool_fundings')->count()
        );

        $this->assertAmount(
            '990',
            DB::table('company_wallet')
                ->where('id', 1)
                ->value('balance')
        );
    }

    public function test_reference_used_by_legacy_generation_is_rejected(): void
    {
        $admin = $this->admin();

        $this->enable();

        DB::table('generation_profit_fundings')->insert([
            'profit_reference' => 'SHARED-001',
            'approved_by' => $admin->id,
            'verified_profit_amount' => '100.00000000',
            'pool_percentage_snapshot' => '30.000000000',
            'pool_amount' => '30.00000000',
            'rounding_unallocated' => '0.00000000',
            'status' => 'funded_unallocated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('another funding system');

        MagicPoolFundingService::fundFromTradingProfit(
            $admin,
            '100',
            'shared-001'
        );
    }

    public function test_insufficient_company_funds_cannot_create_funding(): void
    {
        $admin = $this->admin();

        $this->enable('5.00000000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient');

        MagicPoolFundingService::fundDirectPool(
            $admin,
            '10',
            'FUNDS-001'
        );
    }

    public function test_trading_profit_requires_full_verified_profit_in_company_wallet(): void
    {
        $admin = $this->admin();

        $this->enable('50.00000000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient');

        MagicPoolFundingService::fundFromTradingProfit(
            $admin,
            '100',
            'FUNDS-002'
        );
    }

    public function test_combined_funding_switch_blocks_standalone_magic_funding(): void
    {
        $admin = $this->admin();

        $this->enable();

        DB::table('profit_sharing_settings')
            ->where('id', 1)
            ->update(['combined_funding_enabled' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Combined funding');

        MagicPoolFundingService::fundDirectPool(
            $admin,
            '10',
            'COMBINED-001'
        );
    }

    public function test_magic_payout_switch_blocks_funding(): void
    {
        $admin = $this->admin();

        $this->enable();

        DB::table('magic_income_settings')
            ->where('id', 1)
            ->update(['magic_enabled' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payout switches');

        MagicPoolFundingService::fundDirectPool(
            $admin,
            '10',
            'PAYOUT-001'
        );
    }

    public function test_invalid_money_and_reference_are_rejected(): void
    {
        $admin = $this->admin();

        $this->enable();

        foreach (['0', '-1', '1.000000001', '1e3'] as $amount) {
            try {
                MagicPoolFundingService::fundDirectPool(
                    $admin,
                    $amount,
                    'INVALID-001'
                );

                $this->fail(
                    "Expected invalid amount rejection for {$amount}"
                );
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString(
                    'Invalid positive USDT amount',
                    $exception->getMessage()
                );
            }
        }

        $this->expectException(
            \InvalidArgumentException::class
        );

        MagicPoolFundingService::fundDirectPool(
            $admin,
            '10',
            'bad reference with spaces'
        );
    }

    public function test_reference_used_by_unified_profit_sharing_is_rejected(): void
    {
        $admin = $this->admin();

        $this->enable();

        DB::table('profit_sharing_fundings')->insert([
            'profit_reference' => 'UNIFIED-001',
            'approved_by' => $admin->id,
            'generation_funding_id' =>
                DB::table('generation_profit_fundings')
                    ->insertGetId([
                        'profit_reference' => 'GEN-UNIFIED-001',
                        'approved_by' => $admin->id,
                        'verified_profit_amount' => '100.00000000',
                        'pool_percentage_snapshot' => '30.000000000',
                        'pool_amount' => '30.00000000',
                        'rounding_unallocated' => '0.00000000',
                        'status' => 'funded_unallocated',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]),
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('another funding system');

        MagicPoolFundingService::fundDirectPool(
            $admin,
            '10',
            'unified-001'
        );
    }

    // NEW ROLE-BASED SECURITY TESTS

    public function test_username_admin_with_member_role_cannot_fund(): void
    {
        $fakeAdmin = $this->user('ADMIN', 'member');

        $this->enable();

        try {
            MagicPoolFundingService::fundDirectPool(
                $fakeAdmin,
                '10',
                'FAKE-ADMIN-001'
            );

            $this->fail(
                'Username ADMIN without admin role must be denied.'
            );
        } catch (AuthorizationException $exception) {
            $this->assertSame(
                0,
                DB::table('magic_pool_fundings')->count()
            );

            $this->assertSame(
                0,
                DB::table('financial_ledger')->count()
            );

            $this->assertAmount(
                '1000',
                DB::table('company_wallet')
                    ->where('id', 1)
                    ->value('balance')
            );
        }
    }

    public function test_admin_with_different_username_can_fund(): void
    {
        $admin = $this->admin('FINANCEADMIN');

        $this->enable();

        $fundingId = MagicPoolFundingService::fundDirectPool(
            $admin,
            '10',
            'OTHER-ADMIN-001'
        );

        $this->assertGreaterThan(0, $fundingId);

        $this->assertSame(
            'reserved_no_payout',
            DB::table('magic_pool_fundings')
                ->find($fundingId)
                ->status
        );

        $this->assertAmount(
            '990',
            DB::table('company_wallet')
                ->where('id', 1)
                ->value('balance')
        );
    }

    public function test_super_admin_can_fund(): void
    {
        $admin = $this->admin(
            'SUPERUSER',
            'super_admin'
        );

        $this->enable();

        $fundingId = MagicPoolFundingService::fundDirectPool(
            $admin,
            '10',
            'SUPER-ADMIN-001'
        );

        $this->assertGreaterThan(0, $fundingId);

        $this->assertAmount(
            '10',
            DB::table('magic_pool_fundings')
                ->find($fundingId)
                ->pool_amount
        );

        $this->assertSame(
            0,
            DB::table('financial_ledger')
                ->where('reference_type', 'magic_pool_funding')
                ->whereNotNull('user_id')
                ->count()
        );
    }

    public function test_revoked_admin_cannot_fund_using_stale_model(): void
    {
        $admin = $this->admin();

        $this->enable();

        // Keep the in-memory model stale intentionally.
        DB::table('users')
            ->where('id', $admin->id)
            ->update(['role' => 'member']);

        try {
            MagicPoolFundingService::fundDirectPool(
                $admin,
                '10',
                'REVOKED-ADMIN-001'
            );

            $this->fail(
                'Revoked admin must not authorize funding.'
            );
        } catch (AuthorizationException $exception) {
            $this->assertSame(
                0,
                DB::table('magic_pool_fundings')->count()
            );

            $this->assertSame(
                0,
                DB::table('financial_ledger')->count()
            );

            $this->assertAmount(
                '1000',
                DB::table('company_wallet')
                    ->where('id', 1)
                    ->value('balance')
            );
        }
    }
}
