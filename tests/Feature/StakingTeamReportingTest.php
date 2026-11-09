<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActivationService;
use App\Services\MemberAccountAdministration;
use App\Services\StakingPrincipalService;
use App\Services\StakingService;
use App\Services\TeamCounterService;
use App\Services\TeamLevelViewerService;
use App\Services\WalletService;
use App\Services\WithdrawalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class StakingTeamReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot.member_activation_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
    }

    private function fundedMember(?User $sponsor = null): User
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'inactive', 'sponsor_user_id' => $sponsor?->id, 'sponsor_id' => $sponsor?->username, 'security_pin' => Hash::make('123456')]);
        TeamCounterService::synchronize($member->id);
        $id = DB::table('member_deposit_requests')->insertGetId(['user_id' => $member->id, 'request_key' => 'team-deposit-'.$member->id, 'network' => 'BEP20', 'tx_hash' => '0x'.hash('sha256', (string) $member->id), 'amount' => '100', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        WalletService::credit($member->id, 'activation_balance', '100', 'verified_deposit', 'deposit:credit:'.$id, ['reference_type' => 'member_deposit_request', 'reference_id' => $id]);

        return $member;
    }

    public function test_first_funded_stake_activates_member_and_updates_team_exactly_once(): void
    {
        $sponsor = $this->fundedMember();
        $member = $this->fundedMember($sponsor);
        $key = 'funded_team_stake_001';
        $position = app(StakingService::class)->create($member->id, '50', $key, 'activation_balance');
        $ledger = DB::table('financial_ledger')->count();
        $this->assertSame($position, app(StakingService::class)->create($member->id, '50', $key, 'activation_balance'));
        $this->assertSame('active', $member->fresh()->status);
        $this->assertDatabaseHas('member_teams', ['user_id' => $sponsor->id, 'level' => 1, 'active_count' => 1, 'inactive_count' => 0, 'total_business' => '50']);
        $this->assertSame('50.00000000', $member->fresh()->activation_balance);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->actingAs($sponsor)->get(route('member.detail', ['panel' => 'direct-referrals']))->assertOk()->assertViewHas('referrals', fn ($rows) => $rows->first()->status === 'active');
    }

    public function test_business_adds_packages_and_remaining_funded_stakes_across_levels(): void
    {
        $root = $this->fundedMember();
        $parent = $this->fundedMember($root);
        $member = $this->fundedMember($parent);
        ActivationService::activateSelf($member, '20', (string) Str::uuid());
        app(StakingService::class)->create($member->id, '30', 'team_mixed_sources_001', 'activation_balance');
        TeamCounterService::synchronize($member->id);
        $this->assertDatabaseHas('member_teams', ['user_id' => $parent->id, 'level' => 1, 'active_count' => 1, 'total_business' => '50']);
        $this->assertDatabaseHas('member_teams', ['user_id' => $root->id, 'level' => 2, 'active_count' => 1, 'total_business' => '50']);
    }

    public function test_legacy_status_repair_preserves_money_and_respects_manual_inactivation(): void
    {
        $sponsor = $this->fundedMember();
        $member = $this->fundedMember($sponsor);
        app(StakingService::class)->create($member->id, '50', 'team_legacy_stake_001', 'activation_balance');
        DB::table('users')->where('id', $member->id)->update(['status' => 'inactive']);
        DB::table('member_team_states')->where('user_id', $member->id)->update(['is_active' => false, 'active_business' => '0']);
        DB::table('member_teams')->where('user_id', $sponsor->id)->where('level', 1)->update(['active_count' => 0, 'inactive_count' => 1, 'total_business' => '0']);
        $before = $member->fresh()->only('activation_balance', 'available_balance', 'total_investment', 'lifetime_income');
        $ledger = DB::table('financial_ledger')->count();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        StakingService::repairMemberReporting($admin, $member->id);
        StakingService::repairMemberReporting($admin, $member->id);
        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame($before, $member->fresh()->only('activation_balance', 'available_balance', 'total_investment', 'lifetime_income'));
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->assertDatabaseHas('member_teams', ['user_id' => $sponsor->id, 'level' => 1, 'active_count' => 1, 'inactive_count' => 0, 'total_business' => '50']);
        MemberAccountAdministration::status($admin, $member->id, 'inactive', 'active');
        $this->expectExceptionMessage('deliberately set this account inactive');
        StakingService::repairMemberReporting($admin, $member->id);
    }

    public function test_principal_hold_and_refund_update_business_without_double_counting(): void
    {
        config(['leobot.withdrawals_enabled' => true, 'leobot.withdrawal_settings_write_enabled' => true]);
        config(['leobot_magic.magic_matrix_enrollment_enabled' => true]);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $sponsor = $this->fundedMember();
        $member = $this->fundedMember($sponsor);
        $position = app(StakingService::class)->create($member->id, '50', 'team_withdraw_stake_01', 'activation_balance');
        $wallet = DB::table('usdt_member_wallets')->insertGetId(['user_id' => $member->id, 'network' => 'BEP20', 'address' => '0x'.str_repeat('3', 40), 'verification_method' => 'test_fixture', 'proof_digest' => str_repeat('a', 64), 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        WithdrawalRequestService::setPrincipalFeePercent($admin, '10');
        WithdrawalRequestService::setControls($admin, true, true, true);
        $request = StakingPrincipalService::request($member->fresh(), $position, '10', 'team_principal_request_01', $wallet);
        $this->assertDatabaseHas('member_teams', ['user_id' => $sponsor->id, 'level' => 1, 'total_business' => '40']);
        $this->assertDatabaseMissing('magic_matrix_positions', ['user_id' => $member->id]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        WithdrawalRequestService::reject($admin, $request);
        $this->assertDatabaseHas('magic_matrix_positions', ['user_id' => $member->id]);
        $this->assertDatabaseHas('member_teams', ['user_id' => $sponsor->id, 'level' => 1, 'total_business' => '50']);
    }

    public function test_dashboard_uses_current_statuses_even_when_reporting_cache_is_stale(): void
    {
        $root = User::factory()->create(['role' => 'member', 'security_pin' => Hash::make('123456')]);
        $child = User::factory()->create(['role' => 'member', 'status' => 'inactive', 'sponsor_user_id' => $root->id]);
        TeamCounterService::synchronize($child->id);
        DB::table('users')->where('id', $child->id)->update(['status' => 'active']);
        $before = DB::table('member_teams')->orderBy('id')->get()->toJson();
        $ledger = DB::table('financial_ledger')->count();

        $response = $this->actingAs($root)->get(route('member.detail', ['panel' => 'team-breakdown']))->assertOk();
        $row = $response->viewData('levelRows')->get(1);
        $this->assertSame(1, $row->active_count);
        $this->assertSame(0, $row->inactive_count);
        $this->assertSame(1, $response->viewData('teamSummary')->total_active_team);
        $this->assertSame(0, $response->viewData('teamSummary')->total_inactive_team);
        $this->assertSame($before, DB::table('member_teams')->orderBy('id')->get()->toJson());
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->assertSame('active', $child->fresh()->status);
        Sanctum::actingAs($root, ['member:read']);
        $this->getJson('/api/dashboard-counters')->assertOk()
            ->assertJsonPath('data.overview_counters.total_active_team', 1)
            ->assertJsonPath('data.overview_counters.total_inactive_team', 0)
            ->assertJsonPath('data.level_wise_breakdown.0.active_count', 1)
            ->assertJsonPath('data.level_wise_breakdown.0.inactive_count', 0);

        DB::table('users')->where('id', $child->id)->update(['status' => 'inactive']);
        $response = $this->get(route('member.detail', ['panel' => 'team-breakdown']))->assertOk();
        $this->assertSame(0, $response->viewData('levelRows')->get(1)->active_count);
        $this->assertSame(1, $response->viewData('levelRows')->get(1)->inactive_count);
    }

    public function test_live_status_reporting_is_scoped_to_51_levels_and_stops_sponsor_cycles(): void
    {
        $root = User::factory()->create(['role' => 'member']);
        $parent = $root;
        for ($level = 1; $level <= 52; $level++) {
            $parent = User::factory()->create(['role' => 'member', 'status' => 'active', 'sponsor_user_id' => $parent->id]);
        }
        User::factory()->create(['role' => 'member', 'status' => 'active']);
        $counts = TeamLevelViewerService::statusCounts($root->id);
        $this->assertCount(51, $counts);
        $this->assertSame(51, (int) $counts->sum('active_count'));
        $this->assertFalse($counts->has(52));

        $child = User::factory()->create(['role' => 'member', 'status' => 'inactive', 'sponsor_user_id' => $parent->id]);
        DB::table('users')->where('id', $root->id)->update(['sponsor_user_id' => $child->id]);
        $cycleCounts = TeamLevelViewerService::statusCounts($child->id);
        $this->assertSame(51, (int) $cycleCounts->sum('active_count') + (int) $cycleCounts->sum('inactive_count'));
        $this->assertSame(0, (int) TeamLevelViewerService::statusCounts(User::factory()->create()->id)->sum('active_count'));
        $cycleRoot = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $cycleChild = User::factory()->create(['role' => 'member', 'status' => 'inactive', 'sponsor_user_id' => $cycleRoot->id]);
        DB::table('users')->where('id', $cycleRoot->id)->update(['sponsor_user_id' => $cycleChild->id]);
        $counts = TeamLevelViewerService::statusCounts($cycleRoot->id);
        $this->assertCount(1, $counts);
        $this->assertSame(0, (int) $counts->get(1)->active_count);
        $this->assertSame(1, (int) $counts->get(1)->inactive_count);
    }
}
