<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GenerationAllocationService;
use App\Services\TradingProfitPoolService;
use App\Services\UnifiedProfitSharingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class UnifiedProfitSharingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $username = 'TX001'): User
    {
        $uuid = str_replace('-', '', (string) Str::uuid());
        $id = DB::table('users')->insertGetId([
            'username' => $username,
            'name' => 'Test Member',
            'email' => $uuid . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => 'active',
            'activation_balance' => '0.00000000',
            'available_balance' => '0.00000000',
            'total_investment' => '0.00000000',
            'lifetime_income' => '0.00000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::findOrFail($id);
    }

    private function enable(string $funds = '1000.00000000'): void
    {
        // Test-only opt-in. Production defaults OFF; no HTTP endpoint enables this.
        DB::table('profit_sharing_settings')->where('id', 1)
            ->update(['combined_funding_enabled' => true]);
        DB::table('company_wallet')->where('id', 1)->update(['balance' => $funds]);
    }

    private function eqMoney(string $expect, mixed $actual): void
    {
        $actual = (string) $actual;
        // SQLite tests may return tiny DECIMAL values in exponential notation.
        // Compare fixed decimal strings without relying on binary float rounding.
        if (preg_match('/\A(\d+)(?:\.(\d+))?[eE]([+-]?\d{1,3})\z/D', $actual, $m)) {
            $exponent = (int) $m[3];
            $this->assertGreaterThanOrEqual(-30, $exponent);
            $this->assertLessThanOrEqual(30, $exponent);
            $digits = $m[1] . ($m[2] ?? '');
            $point = strlen($m[1]) + $exponent;
            if ($point <= 0) {
                $actual = '0.' . str_repeat('0', -$point) . $digits;
            } elseif ($point >= strlen($digits)) {
                $actual = $digits . str_repeat('0', $point - strlen($digits));
            } else {
                $actual = substr($digits, 0, $point) . '.' . substr($digits, $point);
            }
        }
        $this->assertSame(0, bccomp($expect, $actual, 8));
    }

    public function test_one_profit_period_creates_self_generation_and_magic_reserves(): void
    {
        $admin = $this->member('ADMIN');
        $memberA = $this->member('TX111111');
        $memberB = $this->member('TX222222');
        $this->enable('1000');
        $id = UnifiedProfitSharingService::fund($admin, '1000', 'PROFIT-OCT-01', [
            $memberA->id => '600.00000000',
            $memberB->id => '400.00000000',
        ]);
        $funding = DB::table('profit_sharing_fundings')->find($id);
        $this->eqMoney('670', $funding->self_amount);
        $this->eqMoney('300', $funding->generation_amount);
        $this->eqMoney('30', $funding->magic_amount);
        $this->eqMoney('1000', $funding->funded_total);
        $this->eqMoney('0', $funding->company_rounding_remainder);
        $this->eqMoney('0', DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->eqMoney('300', DB::table('generation_pool_accounts')->where('id', 1)->value('balance'));
        $reserve = DB::table('profit_sharing_reserve_accounts')->find(1);
        $this->eqMoney('670', $reserve->self_balance);
        $this->eqMoney('30', $reserve->magic_balance);

        $self = DB::table('profit_sharing_self_reserves')
            ->where('funding_id', $id)->orderBy('user_id')->get();
        $this->assertCount(2, $self);
        $shares = $self->mapWithKeys(fn ($r) => [(int) $r->user_id => (string) $r->self_share_reserved]);
        $this->eqMoney('402', $shares[$memberA->id]);
        $this->eqMoney('268', $shares[$memberB->id]);
        $this->assertSame(51, DB::table('generation_level_budgets')
            ->where('funding_id', $funding->generation_funding_id)->count());
        $this->eqMoney('90', DB::table('generation_level_budgets')
            ->where('funding_id', $funding->generation_funding_id)
            ->where('level_number', 1)->value('budget_amount'));

        $entries = DB::table('financial_ledger')
            ->where('reference_type', 'profit_sharing_funding')
            ->where('reference_id', $id)->get();
        $this->assertCount(4, $entries);
        $net = '0.00000000';
        foreach ($entries as $entry) {
            $net = bcadd($net, (string) $entry->amount, 8);
        }
        $this->eqMoney('0', $net);
        $this->eqMoney('0', $memberA->fresh()->available_balance);
        $this->eqMoney('0', $memberB->fresh()->lifetime_income);
        $this->assertSame('reserved_no_payout', $funding->status);
    }

    public function test_duplicate_reference_returns_same_funding_without_recredit(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        $this->enable();
        $id1 = UnifiedProfitSharingService::fund($admin, '1000', 'SAME-PERIOD', [$owner->id => '1000']);
        DB::table('profit_sharing_settings')->where('id', 1)
            ->update(['self_profit_percent' => '65.000000000']);
        $id2 = UnifiedProfitSharingService::fund($admin, '1000.00000000', 'same-period',
            [$owner->id => '1000.00000000']);
        $this->assertSame($id1, $id2);
        $this->assertSame(1, DB::table('profit_sharing_fundings')->count());
        $this->assertSame(1, DB::table('generation_profit_fundings')->count());
        $this->assertSame(4, DB::table('financial_ledger')->count());
    }

    public function test_same_reference_with_different_owner_breakdown_rejected(): void
    {
        $admin = $this->member('ADMIN');
        $first = $this->member('TX1');
        $second = $this->member('TX2');
        $this->enable();
        UnifiedProfitSharingService::fund($admin, '1000', 'PERIOD-OWNER', [$first->id => '1000']);
        $this->expectException(RuntimeException::class);
        UnifiedProfitSharingService::fund($admin, '1000', 'PERIOD-OWNER', [$second->id => '1000']);
    }

    public function test_mismatched_profit_breakdown_has_no_funding(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        $this->enable();
        try {
            UnifiedProfitSharingService::fund($admin, '1000', 'MISMATCH', [$owner->id => '999']);
            $this->fail('Mismatch must fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('breakdown', $e->getMessage());
        }
        $this->assertSame(0, DB::table('profit_sharing_fundings')->count());
        $this->eqMoney('1000', DB::table('company_wallet')->where('id', 1)->value('balance'));
    }

    public function test_disabled_mode_blocks_funding(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        DB::table('company_wallet')->where('id', 1)->update(['balance' => '1000']);
        $this->expectException(RuntimeException::class);
        UnifiedProfitSharingService::fund($admin, '1000', 'DISABLED', [$owner->id => '1000']);
    }

    public function test_only_active_admin_can_fund(): void
    {
        $owner = $this->member();
        $this->enable();
        $this->expectException(AuthorizationException::class);
        UnifiedProfitSharingService::fund($owner, '1000', 'NO-ADMIN', [$owner->id => '1000']);
    }

    public function test_combined_settings_must_total_one_hundred_percent(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        $this->enable();
        DB::table('profit_sharing_settings')->where('id', 1)
            ->update(['self_profit_percent' => '70.000000000']);
        $this->expectException(RuntimeException::class);
        UnifiedProfitSharingService::fund($admin, '1000', 'BAD-PERCENT', [$owner->id => '1000']);
    }

    public function test_custom_seventy_twenty_five_five_percentages(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        $this->enable();
        DB::table('profit_sharing_settings')->where('id', 1)
            ->update(['self_profit_percent' => '70']);
        DB::table('generation_pool_settings')->where('id', 1)
            ->update(['trading_profit_pool_percent' => '25']);
        DB::table('magic_income_settings')->where('id', 1)
            ->update(['trading_profit_pool_percent' => '5']);
        $id = UnifiedProfitSharingService::fund($admin, '1000', 'CUSTOM-PERCENT',
            [$owner->id => '1000']);
        $f = DB::table('profit_sharing_fundings')->find($id);
        $this->eqMoney('700', $f->self_amount);
        $this->eqMoney('250', $f->generation_amount);
        $this->eqMoney('50', $f->magic_amount);
    }

    public function test_insufficient_company_funds_rollback_all_reserves(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        $this->enable('999.99999999');
        $this->expectException(RuntimeException::class);
        try {
            UnifiedProfitSharingService::fund($admin, '1000', 'INSUFFICIENT', [$owner->id => '1000']);
        } finally {
            $this->assertSame(0, DB::table('profit_sharing_fundings')->count());
            $this->assertSame(0, DB::table('generation_profit_fundings')->count());
        }
    }

    public function test_old_generation_only_funding_cannot_bypass_unified_mode(): void
    {
        $admin = $this->member('ADMIN');
        $this->enable();
        $this->expectException(RuntimeException::class);
        TradingProfitPoolService::fund($admin, '1000', 'LEGACY-BLOCKED');
    }

    public function test_previous_generation_only_funding_reference_cannot_be_reused(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        DB::table('company_wallet')->where('id', 1)->update(['balance' => '1000']);
        TradingProfitPoolService::fund($admin, '1000', 'LEGACY-USED');
        DB::table('profit_sharing_settings')->where('id', 1)
            ->update(['combined_funding_enabled' => true]);
        $this->expectException(RuntimeException::class);
        UnifiedProfitSharingService::fund($admin, '1000', 'LEGACY-USED', [$owner->id => '1000']);
    }

    public function test_new_generation_reserve_remains_compatible_with_phase_two(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        $this->enable();
        $id = UnifiedProfitSharingService::fund($admin, '1000', 'PERIOD-ALLOC',
            [$owner->id => '1000']);
        $fundingId = DB::table('profit_sharing_fundings')->find($id)->generation_funding_id;
        $run = GenerationAllocationService::allocate($admin, (int) $fundingId);
        $this->assertGreaterThan(0, $run);
        $this->eqMoney('300', DB::table('generation_pool_accounts')->where('id', 1)->value('balance'));
        $this->eqMoney('670', DB::table('profit_sharing_reserve_accounts')->where('id', 1)->value('self_balance'));
    }
    public function test_fractional_remainder_stays_with_company_and_never_pays(): void
    {
        $admin = $this->member('ADMIN');
        $first = $this->member('TX3');
        $second = $this->member('TX4');
        $this->enable('0.00000006');
        $id = UnifiedProfitSharingService::fund($admin, '0.00000006', 'TINY-PROFIT', [
            $first->id => '0.00000003', $second->id => '0.00000003',
        ]);
        $f = DB::table('profit_sharing_fundings')->find($id);
        $this->eqMoney('0.00000004', $f->self_amount);
        $this->eqMoney('0.00000001', $f->generation_amount);
        $this->eqMoney('0', $f->magic_amount);
        $this->eqMoney('0.00000001', $f->company_rounding_remainder);
        $this->eqMoney('0.00000001', DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->eqMoney('0', $first->fresh()->available_balance);
    }

    public function test_tiny_profit_retry_and_next_period_stay_consistent(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member('TX5');
        $this->enable('0.00000020');

        $first = UnifiedProfitSharingService::fund($admin, '0.00000006', 'TINY-RETRY', [
            $owner->id => '0.00000006',
        ]);
        $retry = UnifiedProfitSharingService::fund($admin, '0.00000006', 'TINY-RETRY', [
            $owner->id => '0.00000006',
        ]);
        $this->assertSame($first, $retry);
        $second = UnifiedProfitSharingService::fund($admin, '0.00000006', 'TINY-NEXT', [
            $owner->id => '0.00000006',
        ]);
        $this->assertNotSame($first, $second);
        $this->assertSame(2, DB::table('profit_sharing_fundings')->count());
        $this->eqMoney('0.00000010', DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->eqMoney('0.00000008', DB::table('profit_sharing_reserve_accounts')
            ->where('id', 1)->value('self_balance'));
        $this->eqMoney('0.00000002', DB::table('generation_pool_accounts')
            ->where('id', 1)->value('balance'));
        $this->eqMoney('0', $owner->fresh()->available_balance);
    }

    public function test_generation_enabled_prevents_staged_funding(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        $this->enable();
        DB::table('generation_pool_settings')->where('id', 1)
            ->update(['generation_enabled' => true]);
        $this->expectException(RuntimeException::class);
        UnifiedProfitSharingService::fund($admin, '1000', 'GEN-ON', [$owner->id => '1000']);
    }

    public function test_no_wallet_or_lifetime_income_changes(): void
    {
        $admin = $this->member('ADMIN');
        $owner = $this->member();
        $this->enable();
        UnifiedProfitSharingService::fund($admin, '1000', 'NO-PAYOUT', [$owner->id => '1000']);
        $fresh = $owner->fresh();
        $this->eqMoney('0', $fresh->available_balance);
        $this->eqMoney('0', $fresh->activation_balance);
        $this->eqMoney('0', $fresh->lifetime_income);
    }

}
