<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class StakingSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['leobot.staking_enabled' => true, 'leobot.production_financial_approval' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
    }

    private function member(): User
    {
        return User::factory()->create(['role' => 'member', 'status' => 'active', 'available_balance' => '100.00000000',
            'security_pin' => Hash::make('123456')]);
    }

    public function test_stake_and_retry_create_exactly_one_position_debit_and_ledger(): void
    {
        $member = $this->member();
        $service = new StakingService;
        $id = $service->create($member->id, '25.00000001', 'stake_request_0001');
        $this->assertSame($id, $service->create($member->id, '25.00000001', 'stake_request_0001'));
        $this->assertSame('74.99999999', $member->fresh()->available_balance);
        $this->assertDatabaseCount('staking_positions', 1);
        $this->assertDatabaseCount('financial_ledger', 2);
        $this->assertDatabaseHas('financial_ledger', ['transaction_key' => 'staking:stake_request_0001',
            'amount' => '-25.00000001', 'balance_before' => '100.00000000', 'balance_after' => '74.99999999',
            'reference_type' => 'staking_position', 'reference_id' => $id]);
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amount_leaves_all_financial_state_unchanged(string $amount): void
    {
        $member = $this->member();
        try {
            (new StakingService)->create($member->id, $amount, 'stake_request_0001');
            $this->fail('Invalid stake accepted');
        } catch (RuntimeException) {
            $this->assertSame('100.00000000', $member->fresh()->available_balance);
            $this->assertDatabaseCount('staking_positions', 0);
            $this->assertDatabaseCount('financial_ledger', 0);
        }
    }

    public static function invalidAmounts(): array
    {
        return [['0'], ['-1'], ['1e2'], ['0.000000001'], ['1000000000000'], ['101'], ["1\n"]];
    }

    public function test_ledger_insert_failure_rolls_back_position_and_wallet_update(): void
    {
        $member = $this->member();
        DB::statement('ALTER TABLE financial_ledger ADD CONSTRAINT test_reject_debit CHECK (amount >= 0) NOT VALID');
        try {
            try {
                (new StakingService)->create($member->id, '25', 'stake_request_0001');
                $this->fail('Ledger failure expected');
            } catch (QueryException) {
                $this->assertSame('100.00000000', $member->fresh()->available_balance);
                $this->assertDatabaseCount('staking_positions', 0);
                $this->assertDatabaseCount('financial_ledger', 0);
            }
        } finally {
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT test_reject_debit');
        }
    }

    public function test_request_key_cannot_be_reused_with_another_amount_or_member(): void
    {
        $a = $this->member();
        $b = $this->member();
        $service = new StakingService;
        $service->create($a->id, '25', 'stake_request_0001');
        foreach ([[$a->id, '26'], [$b->id, '25']] as [$userId, $amount]) {
            try {
                $service->create($userId, $amount, 'stake_request_0001');
                $this->fail('Conflicting request accepted');
            } catch (RuntimeException $e) {
                $this->assertSame('Request key conflict', $e->getMessage());
            }
        }
        $this->assertSame('75.00000000', $a->fresh()->available_balance);
        $this->assertSame('100.00000000', $b->fresh()->available_balance);
        $this->assertDatabaseCount('financial_ledger', 2);
    }

    public function test_approval_gate_blocks_http_activation_and_direct_staking(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = $this->member();
        config(['leobot.production_financial_approval' => false]);
        $this->actingAs($admin)->postJson(route('admin.staking.toggle'), ['enabled' => true])->assertStatus(409);
        $this->expectExceptionMessage('Staking requires financial approval');
        (new StakingService)->create($member->id, '25', 'stake_request_0001');
    }

    public function test_independent_staking_gate_cannot_be_bypassed(): void
    {
        $member = $this->member();
        config(['leobot.staking_enabled' => false]);
        $this->expectExceptionMessage('Staking requires financial approval');
        (new StakingService)->create($member->id, '25', 'stake_request_0001');
    }

    public function test_off_switch_stops_new_stakes_and_preserves_existing_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = $this->member();
        $service = new StakingService;
        $service->create($member->id, '25', 'stake_request_0001');
        config(['leobot.production_financial_approval' => false]);
        $this->actingAs($admin)->postJson(route('admin.staking.toggle'), ['enabled' => false])->assertOk();
        try {
            $service->create($member->id, '25', 'stake_request_0002');
            $this->fail('OFF switch bypassed');
        } catch (RuntimeException $e) {
            $this->assertSame('Staking disabled', $e->getMessage());
        }
        $this->assertSame('75.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseCount('staking_positions', 1);
        $this->assertDatabaseCount('financial_ledger', 2);
        $this->assertDatabaseHas('staking_control_audits', ['admin_id' => $admin->id, 'old_enabled' => true, 'new_enabled' => false]);
    }

    public function test_stale_admin_object_cannot_enable_staking_after_revocation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        DB::table('users')->where('id', $admin->id)->update(['role' => 'member']);
        $this->expectException(AuthorizationException::class);
        (new StakingService)->toggle($admin, true);
    }

    public function test_authorized_admin_can_enable_only_with_both_approvals(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => false]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Turn Staking ON')->assertDontSee('Turn Staking ON (locked)');
        config(['leobot.staking_enabled' => false]);
        $this->postJson(route('admin.staking.toggle'), ['enabled' => true])->assertStatus(409);
        $this->assertDatabaseHas('staking_controls', ['id' => 1, 'enabled' => false]);
        config(['leobot.staking_enabled' => true]);
        $this->postJson(route('admin.staking.toggle'), ['enabled' => true])->assertOk();
        $this->assertDatabaseHas('staking_controls', ['id' => 1, 'enabled' => true]);
        $this->assertDatabaseHas('staking_control_audits', ['admin_id' => $admin->id, 'old_enabled' => false, 'new_enabled' => true]);
    }

    public function test_failed_control_audit_rolls_back_switch_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        DB::statement('ALTER TABLE staking_control_audits ADD CONSTRAINT test_reject_control_audit CHECK (false) NOT VALID');
        try {
            $this->actingAs($admin)->postJson(route('admin.staking.toggle'), ['enabled' => false])->assertStatus(409);
            $this->assertDatabaseHas('staking_controls', ['id' => 1, 'enabled' => true]);
            $this->assertDatabaseCount('staking_control_audits', 0);
        } finally {
            DB::statement('ALTER TABLE staking_control_audits DROP CONSTRAINT test_reject_control_audit');
        }
    }

    public function test_numeric_json_amount_is_rejected_without_financial_writes(): void
    {
        $this->actingAs($this->member())->postJson(route('member.staking.store'), ['amount' => 25, 'request_key' => 'stake_request_0001'])->assertUnprocessable();
        $this->assertDatabaseCount('staking_positions', 0);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_guest_member_and_blocked_admin_cannot_toggle(): void
    {
        $this->postJson(route('admin.staking.toggle'), ['enabled' => true])->assertUnauthorized();
        $this->actingAs($this->member())->postJson(route('admin.staking.toggle'), ['enabled' => true])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_blocked' => true]);
        $this->actingAs($admin)->postJson(route('admin.staking.toggle'), ['enabled' => true])->assertForbidden();
        $this->assertDatabaseCount('staking_control_audits', 0);
    }

    public function test_pin_setup_and_retirement_are_enforced(): void
    {
        $member = $this->member();
        $member->forceFill(['security_pin' => null])->save();
        $this->actingAs($member)->postJson(route('member.staking.store'), ['amount' => '25', 'request_key' => 'stake_request_0001'])->assertForbidden();
        $member->forceFill(['deleted_at' => now()])->save();
        $this->expectExceptionMessage('Member not eligible');
        (new StakingService)->create($member->id, '25', 'stake_request_0001');
    }
}
