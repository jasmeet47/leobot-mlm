<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActivationService;
use App\Services\MemberAccountAdministration;
use App\Services\StakingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MemberProfileAndBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeric_public_id_and_spaced_public_id_search_find_member_only(): void
    {
        $member = User::factory()->create(['username' => 'TX656935', 'role' => 'member']);
        $this->assertSame($member->id, MemberAccountAdministration::find('656935')->id);
        $this->assertSame($member->id, MemberAccountAdministration::find(" tx 656935\u{200B} ")->id);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.member-access', ['member' => '656935']))->assertOk()->assertSee('TX656935');
        $this->assertNull(MemberAccountAdministration::find($admin->username));
    }

    public function test_profile_correction_requires_password_and_cannot_change_role_or_wallets(): void
    {
        $member = User::factory()->create(['role' => 'member', 'security_pin' => Hash::make('123456'), 'password' => Hash::make('Secure-Password123!')]);
        $before = $member->fresh()->getAttributes();
        $this->actingAs($member)->post(route('member.profile.details'), ['name' => 'Correct Name', 'phone' => '+91 98765-43210', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->post(route('member.profile.details'), ['name' => 'Correct Name', 'phone' => '+91 98765-43210', 'current_password' => 'Secure-Password123!', 'role' => 'admin', 'available_balance' => '1000'])->assertSessionHas('success');
        $member->refresh();
        $this->assertSame('Correct Name', $member->name);
        $this->assertSame('+919876543210', $member->phone);
        foreach (['role', 'sponsor_user_id', 'activation_balance', 'available_balance', 'total_investment'] as $field) {
            $this->assertSame($before[$field], $member->getAttributes()[$field]);
        }
        $this->assertDatabaseHas('member_security_audits', ['actor_id' => $member->id, 'action' => 'member_profile_corrected']);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_dashboard_investment_includes_packages_and_remaining_staking_without_changing_wallets(): void
    {
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot.member_activation_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);
        WalletService::credit($member->id, 'activation_balance', '100', 'test_fixture', 'balance_report_fixture');
        ActivationService::activateSelf($member, '10', (string) Str::uuid());
        app(StakingService::class)->create($member->id, '60', 'dashboard_staking_fixture', 'activation_balance');
        $ledger = DB::table('financial_ledger')->count();
        $this->actingAs($member->fresh())->get(route('member.dashboard'))->assertOk()->assertViewHas('currentInvestment', '70.00000000')->assertViewHas('activeActivation', '10.00000000')->assertViewHas('activeStaking', '60.00000000')->assertSee('30.00000000');
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->assertSame('10.00000000', $member->fresh()->total_investment);
    }
}
