<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests for read-only 51-level commission previews.
 * These tests run against the PHPUnit SQLite :memory: database.
 * No real commissions, reservations, or transfers are made.
 */
class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(?int $sponsorId = null, string $status = 'inactive'): User
    {
        $unique = str_replace('-', '', (string) Str::uuid());

        $id = DB::table('users')->insertGetId([
            'username' => 'CP' . substr($unique, 0, 12),
            'name' => 'Commission Preview Member',
            'email' => $unique . '@example.test',
            'phone' => '9000000000',
            'sponsor_user_id' => $sponsorId,
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => Hash::make('123456'),
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

    private function createActivePackage(User $member, string $amount = '1.00000000'): void
    {
        DB::table('activation_packages')->insert([
            'user_id' => $member->id,
            'activation_key' => (string) Str::uuid(),
            'investment_amount' => $amount,
            'status' => 'active',
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertUsdt(string $expected, mixed $actual): void
    {
        $this->assertSame(0, bccomp($expected, (string) $actual, 8));
    }

    private function assertPercent(string $expected, mixed $actual): void
    {
        $this->assertSame(0, bccomp($expected, (string) $actual, 9));
    }

    private function level(array $preview, int $number): array
    {
        $row = $preview['levels'][$number - 1];
        $this->assertSame($number, $row['level']);

        return $row;
    }

    private function assertNoFinancialSideEffects(array $memberIds = []): void
    {
        $this->assertSame(0, DB::table('financial_ledger')->count());
        $this->assertUsdt('0', DB::table('company_wallet')->where('id', 1)->value('balance'));

        foreach ($memberIds as $memberId) {
            $member = User::findOrFail($memberId);
            $this->assertUsdt('0', $member->available_balance);
            $this->assertUsdt('0', $member->activation_balance);
            $this->assertUsdt('0', $member->lifetime_income);
        }
    }

    public function test_investment_preview_uses_five_percent_budget_and_all_51_rates(): void
    {
        $preview = CommissionService::previewInvestment('100.00000000');

        $this->assertSame('investment', $preview['source_type']);
        $this->assertFalse($preview['distribution_enabled']);
        $this->assertTrue($preview['is_preview_only']);
        $this->assertCount(51, $preview['levels']);
        $this->assertPercent('5', $preview['pool_percent']);
        $this->assertPercent('100', $preview['rate_total_percent']);
        $this->assertUsdt('5', $preview['pool_amount']);
        $this->assertUsdt('5', $preview['potential_total']);
        $this->assertUsdt('0', $preview['undistributed_preview_amount']);
        $this->assertUsdt('1.5', $this->level($preview, 1)['potential_amount']);
        $this->assertUsdt('0.125', $this->level($preview, 17)['potential_amount']);
        $this->assertUsdt('0.025', $this->level($preview, 22)['potential_amount']);
        $this->assertUsdt('0.0125', $this->level($preview, 42)['potential_amount']);
        $this->assertNoFinancialSideEffects();
    }

    public function test_trading_profit_preview_uses_thirty_percent_generation_pool(): void
    {
        $preview = CommissionService::previewTradingProfit('100.00000000');

        $this->assertSame('trading_profit', $preview['source_type']);
        $this->assertFalse($preview['distribution_enabled']);
        $this->assertCount(51, $preview['levels']);
        $this->assertPercent('30', $preview['pool_percent']);
        $this->assertPercent('100', $preview['rate_total_percent']);
        $this->assertUsdt('30', $preview['pool_amount']);
        $this->assertUsdt('30', $preview['potential_total']);
        $this->assertUsdt('9', $this->level($preview, 1)['potential_amount']);
        $this->assertUsdt('0.75', $this->level($preview, 17)['potential_amount']);
        $this->assertUsdt('0.15', $this->level($preview, 22)['potential_amount']);
        $this->assertUsdt('0.075', $this->level($preview, 42)['potential_amount']);
        $this->assertNoFinancialSideEffects();
    }

    public function test_custom_admin_percentages_are_read_from_both_settings_tables(): void
    {
        DB::table('investment_distribution_settings')
            ->where('id', 1)->update(['distribution_percent' => '7.000000000']);
        DB::table('generation_pool_settings')
            ->where('id', 1)->update(['trading_profit_pool_percent' => '20.000000000']);

        $this->assertUsdt('7', CommissionService::previewInvestment('100')['pool_amount']);
        $this->assertUsdt('20', CommissionService::previewTradingProfit('100')['pool_amount']);
        $this->assertNoFinancialSideEffects();
    }

    public function test_preview_stays_read_only_even_if_setting_is_enabled(): void
    {
        DB::table('investment_distribution_settings')
            ->where('id', 1)->update(['is_enabled' => true]);
        DB::table('generation_pool_settings')
            ->where('id', 1)->update(['generation_enabled' => true]);

        $this->assertTrue(CommissionService::previewInvestment('100')['distribution_enabled']);
        $this->assertTrue(CommissionService::previewTradingProfit('100')['distribution_enabled']);
        $this->assertNoFinancialSideEffects();
    }

    public function test_disabled_level_is_not_included_in_potential_total(): void
    {
        DB::table('investment_distribution_level_rates')
            ->where('level_number', 1)->update(['is_active' => false]);

        $preview = CommissionService::previewInvestment('100');

        $this->assertFalse($this->level($preview, 1)['is_active']);
        $this->assertUsdt('0', $this->level($preview, 1)['potential_amount']);
        $this->assertUsdt('3.5', $preview['potential_total']);
        $this->assertUsdt('1.5', $preview['undistributed_preview_amount']);
    }

    public function test_max_distribution_level_limits_preview_without_changing_rates(): void
    {
        DB::table('investment_distribution_settings')
            ->where('id', 1)->update(['max_distribution_level' => 1]);

        $preview = CommissionService::previewInvestment('100');

        $this->assertSame(1, $preview['max_distribution_level']);
        $this->assertTrue($this->level($preview, 1)['within_max_level']);
        $this->assertFalse($this->level($preview, 2)['within_max_level']);
        $this->assertUsdt('1.5', $preview['potential_total']);
        $this->assertUsdt('3.5', $preview['undistributed_preview_amount']);
    }

    public function test_invalid_money_amount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CommissionService::previewInvestment('0');
    }

    public function test_more_than_eight_amount_decimals_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CommissionService::previewTradingProfit('1.123456789');
    }

    public function test_commission_rates_must_total_exactly_one_hundred_percent(): void
    {
        DB::table('level_settings')
            ->where('level_number', 22)
            ->update(['commission_percentage' => '0.600000000']);

        $this->expectException(RuntimeException::class);
        CommissionService::previewTradingProfit('100');
    }

    public function test_member_without_any_upline_has_only_unallocated_preview(): void
    {
        $source = $this->createMember();

        $preview = CommissionService::previewInvestmentForMember($source->id, '100');

        $this->assertSame($source->id, $preview['source_member_id']);
        $this->assertCount(51, $preview['levels']);
        $this->assertUsdt('0', $preview['qualified_preview_total']);
        $this->assertUsdt('0', $preview['held_preview_total']);
        $this->assertUsdt('5', $preview['unallocated_preview_total']);
        $this->assertSame('unallocated_missing_upline', $this->level($preview, 1)['qualification_status']);
        $this->assertFalse($preview['funds_reserved']);
        $this->assertFalse($preview['wallets_credited']);
        $this->assertNoFinancialSideEffects([$source->id]);
    }

    public function test_active_direct_sponsor_with_active_package_has_qualified_preview(): void
    {
        $sponsor = $this->createMember(null, 'active');
        $source = $this->createMember($sponsor->id);
        $this->createActivePackage($sponsor);

        $preview = CommissionService::previewInvestmentForMember($source->id, '100');

        $this->assertSame($sponsor->id, $this->level($preview, 1)['recipient_user_id']);
        $this->assertSame('qualified_preview_only', $this->level($preview, 1)['qualification_status']);
        $this->assertUsdt('1.5', $preview['qualified_preview_total']);
        $this->assertUsdt('0', $preview['held_preview_total']);
        $this->assertUsdt('3.5', $preview['unallocated_preview_total']);
        $this->assertNoFinancialSideEffects([$source->id, $sponsor->id]);
    }

    public function test_inactive_sponsor_share_is_held_in_preview_only(): void
    {
        $sponsor = $this->createMember(null, 'inactive');
        $source = $this->createMember($sponsor->id);

        $preview = CommissionService::previewInvestmentForMember($source->id, '100');

        $this->assertSame('held_recipient_inactive', $this->level($preview, 1)['qualification_status']);
        $this->assertUsdt('0', $preview['qualified_preview_total']);
        $this->assertUsdt('1.5', $preview['held_preview_total']);
        $this->assertUsdt('3.5', $preview['unallocated_preview_total']);
        $this->assertNoFinancialSideEffects([$source->id, $sponsor->id]);
    }

    public function test_active_sponsor_without_active_package_is_held_in_preview(): void
    {
        $sponsor = $this->createMember(null, 'active');
        $source = $this->createMember($sponsor->id);

        $preview = CommissionService::previewInvestmentForMember($source->id, '100');

        $this->assertSame('held_no_active_package', $this->level($preview, 1)['qualification_status']);
        $this->assertUsdt('1.5', $preview['held_preview_total']);
        $this->assertNoFinancialSideEffects([$source->id, $sponsor->id]);
    }

    public function test_level_two_sponsor_without_required_team_business_is_held(): void
    {
        $grandparent = $this->createMember(null, 'active');
        $directSponsor = $this->createMember($grandparent->id, 'active');
        $source = $this->createMember($directSponsor->id);
        $this->createActivePackage($grandparent, '1');
        $this->createActivePackage($directSponsor, '1');

        $preview = CommissionService::previewInvestmentForMember($source->id, '100');

        $this->assertSame('qualified_preview_only', $this->level($preview, 1)['qualification_status']);
        $this->assertSame('held_business_not_qualified', $this->level($preview, 2)['qualification_status']);
        $this->assertUsdt('1.5', $preview['qualified_preview_total']);
        $this->assertUsdt('0.25', $preview['held_preview_total']);
        $this->assertUsdt('3.25', $preview['unallocated_preview_total']);
        $this->assertNoFinancialSideEffects([$source->id, $directSponsor->id, $grandparent->id]);
    }

    public function test_trading_profit_member_preview_is_not_a_global_payout(): void
    {
        $sponsor = $this->createMember(null, 'active');
        $source = $this->createMember($sponsor->id);
        $this->createActivePackage($sponsor);

        $preview = CommissionService::previewTradingProfitForMember($source->id, '100');

        $this->assertSame('trading_profit', $preview['source_type']);
        $this->assertUsdt('30', $preview['pool_amount']);
        $this->assertUsdt('9', $preview['qualified_preview_total']);
        $this->assertUsdt('21', $preview['unallocated_preview_total']);
        $this->assertTrue($preview['is_preview_only']);
        $this->assertNoFinancialSideEffects([$source->id, $sponsor->id]);
    }

    public function test_small_amount_rounding_remainder_is_reconciled(): void
    {
        $source = $this->createMember();
        $preview = CommissionService::previewInvestmentForMember($source->id, '0.00001000');

        $this->assertUsdt('0.00000050', $preview['pool_amount']);
        $this->assertSame(1, bccomp((string) $preview['rounding_remainder'], '0', 8));

        $reconciled = bcadd(
            bcadd($preview['qualified_preview_total'], $preview['held_preview_total'], 8),
            $preview['unallocated_preview_total'],
            8
        );

        $this->assertUsdt($preview['pool_amount'], $reconciled);
        $this->assertNoFinancialSideEffects([$source->id]);
    }
}
