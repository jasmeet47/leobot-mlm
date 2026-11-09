<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActivationService;
use App\Services\MagicMatrixEnrollmentService;
use App\Services\MagicMatrixTeamReadService;
use App\Services\StakingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MagicMatrixEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['leobot.member_activation_enabled' => true, 'leobot_magic.magic_matrix_enrollment_enabled' => true, 'leobot_magic.production_financial_approval' => true]);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true, 'minimum_tier_usdt' => '50', 'earning_cap_multiplier' => 3]);
    }

    private function member(): User
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        WalletService::credit($member->id, 'activation_balance', '100', 'test_fixture', 'magic_enrollment_fixture_'.$member->id);

        return $member;
    }

    private function activate(User $member, string $amount = '50'): int
    {
        return ActivationService::activateSelf($member, $amount, (string) Str::uuid());
    }

    public function test_self_activation_places_exact_fifty_and_team_genealogy_shows_children(): void
    {
        $root = $this->member();
        $child = $this->member();
        $this->activate($root);
        $this->activate($child);
        $team = MagicMatrixTeamReadService::forMember($root->id);
        $this->assertTrue($team['has_position']);
        $this->assertSame(1, $team['direct']);
        $this->assertSame(1, $team['total']);
        $this->assertDatabaseHas('magic_matrix_cycles', ['user_id' => $root->id, 'tier_usdt' => '50', 'earning_cap_usdt' => '150', 'magic_paid_usdt' => '0']);
        $this->assertSame('50.00000000', $root->fresh()->activation_balance);
        $this->assertSame('0.00000000', $root->fresh()->available_balance);
    }

    public function test_enrollment_off_then_admin_repair_places_once_without_additional_debit_or_payout(): void
    {
        $member = $this->member();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $this->activate($member);
        $this->assertDatabaseCount('magic_matrix_positions', 0);
        $this->assertStringContainsString('OFF', MagicMatrixEnrollmentService::inspect($member->id)['reason']);
        $ledger = DB::table('financial_ledger')->count();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        $cycle = MagicMatrixEnrollmentService::synchronize($member->id, $admin);
        $this->assertSame($cycle, MagicMatrixEnrollmentService::synchronize($member->id, $admin));
        $this->assertDatabaseCount('magic_matrix_positions', 1);
        $this->assertDatabaseCount('magic_matrix_cycles', 1);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->assertSame('50.00000000', $member->fresh()->activation_balance);
        $this->assertSame('0.00000000', $member->fresh()->available_balance);
        $this->assertSame(1, DB::table('member_security_audits')->where('action', 'magic_matrix_enrollment_synchronized')->count());
    }

    public function test_two_smaller_packages_do_not_qualify_and_unfunded_package_is_rejected(): void
    {
        $member = $this->member();
        $this->activate($member, '25');
        $this->activate($member, '25');
        $this->assertFalse(MagicMatrixEnrollmentService::inspect($member->id)['can_place']);
        $this->assertDatabaseCount('magic_matrix_positions', 0);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $other = $this->member();
        $id = $this->activate($other);
        DB::table('financial_ledger')->where('reference_type', 'activation_package')->where('reference_id', $id)->delete();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        $this->assertFalse(MagicMatrixEnrollmentService::inspect($other->id)['can_place']);
    }

    public function test_admin_repair_route_rejects_members_and_respects_server_gate(): void
    {
        $member = $this->member();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $this->activate($member);
        $this->actingAs($member)->post(route('admin.magic.enroll'), ['member_id' => $member->id])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        config(['leobot_magic.magic_matrix_enrollment_enabled' => false]);
        $this->actingAs($admin)->post(route('admin.magic.enroll'), ['member_id' => $member->id])->assertNotFound();
        config(['leobot_magic.magic_matrix_enrollment_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        $this->get(route('admin.magic.index', ['member' => $member->username]))->assertOk()->assertSee('Place qualified member');
        $this->post(route('admin.magic.enroll'), ['member_id' => $member->id])->assertSessionHas('status');
        $this->assertDatabaseCount('magic_matrix_positions', 1);
    }

    public function test_member_login_does_not_offer_admin_menu_but_direct_admin_login_stays_available(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('href="'.route('admin.login').'"', false);
        $this->get('/admin/login')->assertOk()->assertSee('Admin Login');
    }

    public function test_enabling_switches_automatically_places_waiting_funded_members_once(): void
    {
        config(['leobot_magic.magic_matrix_settings_write_enabled' => true]);
        $root = $this->member();
        $this->activate($root);
        $existingPositions = DB::table('magic_matrix_positions')->orderBy('id')->get()->toJson();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $waiting = $this->member();
        $this->activate($waiting);
        $ledger = DB::table('financial_ledger')->count();
        $balance = $waiting->fresh()->only('activation_balance', 'available_balance', 'total_investment', 'lifetime_income');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $data = ['global_enabled' => 1, 'enrollment_enabled' => 1, 'topup_enabled' => 0, 'settlement_enabled' => 0];
        $this->actingAs($admin)->post(route('admin.magic.switches'), $data)->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertDatabaseHas('magic_matrix_positions', ['user_id' => $waiting->id]);
        $this->assertSame($existingPositions, DB::table('magic_matrix_positions')->where('user_id', $root->id)->orderBy('id')->get()->toJson());
        $this->post(route('admin.magic.switches'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('magic_matrix_positions', 2);
        $this->assertDatabaseCount('magic_matrix_cycles', 2);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->assertSame($balance, $waiting->fresh()->only('activation_balance', 'available_balance', 'total_investment', 'lifetime_income'));
    }

    public function test_global_enable_catches_up_but_server_off_and_unfunded_sources_never_place(): void
    {
        $member = $this->member();
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => false]);
        $this->activate($member);
        $this->assertSame(0, MagicMatrixEnrollmentService::reconcilePending());
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        config(['leobot_magic.magic_matrix_enrollment_enabled' => false]);
        $this->assertSame(0, MagicMatrixEnrollmentService::reconcilePending());
        config(['leobot_magic.magic_matrix_enrollment_enabled' => true]);
        $this->assertSame(1, MagicMatrixEnrollmentService::reconcilePending());
        $this->assertSame(0, MagicMatrixEnrollmentService::reconcilePending());
    }

    public function test_idempotent_activation_retry_recovers_placement_without_a_second_debit(): void
    {
        $member = $this->member();
        $key = (string) Str::uuid();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $package = ActivationService::activateSelf($member, '50', $key);
        $ledger = DB::table('financial_ledger')->count();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        $this->assertSame($package, ActivationService::activateSelf($member, '50', $key));
        $this->assertSame($package, ActivationService::activateSelf($member, '50', $key));
        $this->assertDatabaseCount('magic_matrix_positions', 1);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->assertSame('50.00000000', $member->fresh()->activation_balance);
    }

    public function test_stake_retry_recovers_placement_without_a_second_debit_and_check_command_is_read_only(): void
    {
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $member = $this->member();
        WalletService::credit($member->id, 'available_balance', '50', 'test_fixture', 'magic_stake_retry_funding');
        $service = app(StakingService::class);
        $stake = $service->create($member->id, '50', 'magic_retry_stake_001');
        $ledger = DB::table('financial_ledger')->count();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        $this->artisan('magic:reconcile-enrollment', ['--check' => true])->assertExitCode(1);
        $this->assertDatabaseCount('magic_matrix_positions', 0);
        $this->assertSame($stake, $service->create($member->id, '50', 'magic_retry_stake_001'));
        $this->assertSame($stake, $service->create($member->id, '50', 'magic_retry_stake_001'));
        $this->assertDatabaseCount('magic_matrix_positions', 1);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->artisan('magic:reconcile-enrollment')->assertExitCode(0);
        $this->assertDatabaseCount('magic_matrix_cycles', 1);
        $this->assertSame('0.00000000', $member->fresh()->available_balance);
    }

    public function test_reconciliation_does_not_add_small_packages_or_unfunded_or_blocked_sources(): void
    {
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $small = $this->member();
        $this->activate($small, '20');
        $this->activate($small, '30');
        $unfunded = $this->member();
        $package = $this->activate($unfunded);
        DB::table('financial_ledger')->where('reference_type', 'activation_package')->where('reference_id', $package)->delete();
        $blocked = $this->member();
        $this->activate($blocked);
        $blocked->forceFill(['is_blocked' => true])->save();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        $this->assertSame(0, MagicMatrixEnrollmentService::reconcilePending());
        $this->assertDatabaseCount('magic_matrix_positions', 0);
    }

    public function test_catchup_preserves_qualification_order_and_command_does_not_duplicate_positions(): void
    {
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $later = $this->member();
        $earlier = $this->member();
        $this->activate($earlier);
        $this->travel(1)->minutes();
        $this->activate($later);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        $this->assertSame([$earlier->id, $later->id], MagicMatrixEnrollmentService::pendingMemberIds());
        $ledger = DB::table('financial_ledger')->count();
        $this->artisan('magic:reconcile-enrollment')->assertExitCode(0);
        $this->assertSame([$earlier->id, $later->id], DB::table('magic_matrix_positions')->orderBy('id')->pluck('user_id')->all());
        $this->artisan('magic:reconcile-enrollment')->assertExitCode(0);
        $this->assertDatabaseCount('magic_matrix_positions', 2);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->travelBack();
    }

    public function test_failed_catchup_rolls_back_switches_and_placement_together(): void
    {
        config(['leobot_magic.magic_matrix_settings_write_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $member = $this->member();
        $this->activate($member);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['max_depth' => 1]);
        $ledger = DB::table('financial_ledger')->count();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('admin.magic.switches'), ['global_enabled' => 1, 'enrollment_enabled' => 1, 'topup_enabled' => 0, 'settlement_enabled' => 0]);
            $this->fail('Invalid matrix policy must prevent catchup.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('20 levels / 6 days', $error->getMessage());
        }
        $this->assertFalse((bool) DB::table('magic_matrix_settings')->where('id', 1)->value('enrollment_enabled'));
        $this->assertDatabaseCount('magic_matrix_positions', 0);
        $this->assertDatabaseCount('magic_matrix_settings_audits', 0);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
    }

    public function test_unblocking_a_funded_member_recovers_placement_without_changing_money(): void
    {
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false]);
        $member = $this->member();
        $this->activate($member);
        $member->forceFill(['is_blocked' => true])->save();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        $this->assertSame(0, MagicMatrixEnrollmentService::reconcilePending());
        $ledger = DB::table('financial_ledger')->count();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->post(route('admin.members.unblock', $member->id), ['reason' => 'Member ownership reviewed'])->assertRedirect();
        $this->assertDatabaseHas('magic_matrix_positions', ['user_id' => $member->id]);
        $this->assertSame('50.00000000', $member->fresh()->activation_balance);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
    }

    public function test_leaf_placement_displays_actual_member_and_parent_ids_without_changing_records(): void
    {
        $root = $this->member();
        $leaf = $this->member();
        $outsider = $this->member();
        $this->activate($root);
        $this->activate($leaf);
        $leaf->forceFill(['security_pin' => Hash::make('123456')])->save();
        $positions = DB::table('magic_matrix_positions')->orderBy('id')->get()->toJson();
        $cycles = DB::table('magic_matrix_cycles')->orderBy('id')->get()->toJson();
        $ledger = DB::table('financial_ledger')->count();
        $team = MagicMatrixTeamReadService::forMember($leaf->id);
        $this->assertTrue($team['has_position']);
        $this->assertSame(0, $team['total']);
        $this->assertSame($leaf->username, $team['placement']['username']);
        $this->assertSame($root->username, $team['placement']['parent_username']);
        $this->assertSame([], $team['members']);
        $rootTeam = MagicMatrixTeamReadService::forMember($root->id);
        $this->assertSame($leaf->username, $rootTeam['members'][0]['username']);
        $this->assertSame($leaf->name, $rootTeam['members'][0]['name']);
        $this->actingAs($leaf)->get(route('member.detail', ['panel' => 'magic-team']))->assertOk()
            ->assertSee('Your Magic Matrix Placement')->assertSee($leaf->username)->assertSee($root->username)
            ->assertSee('Placed in Magic Matrix')->assertSee('No members are placed below your position yet.')
            ->assertDontSee($outsider->username);
        $this->assertSame($positions, DB::table('magic_matrix_positions')->orderBy('id')->get()->toJson());
        $this->assertSame($cycles, DB::table('magic_matrix_cycles')->orderBy('id')->get()->toJson());
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
    }

    public function test_unplaced_member_is_not_shown_a_placed_confirmation(): void
    {
        $member = $this->member();
        $member->forceFill(['security_pin' => Hash::make('123456')])->save();
        $this->actingAs($member)->get(route('member.detail', ['panel' => 'magic-team']))->assertOk()
            ->assertSee('No Matrix position is recorded for your ID yet.')
            ->assertDontSee('Placed in Magic Matrix')->assertDontSee('Your ID is placed.');
        $this->assertDatabaseCount('magic_matrix_positions', 0);
    }

    public function test_single_switch_updates_only_target_and_enforces_admin_authorization(): void
    {
        config(['leobot_magic.magic_matrix_settings_write_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['topup_enabled' => false, 'settlement_enabled' => false]);
        $this->actingAs($admin)->post(route('admin.magic.switches'), ['switch_name' => 'topup_enabled', 'enabled' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('magic_matrix_settings', ['id' => 1, 'topup_enabled' => true, 'settlement_enabled' => false, 'enrollment_enabled' => true]);
        $this->assertTrue((bool) DB::table('magic_income_settings')->where('id', 1)->value('magic_enabled'));
        $this->post(route('admin.magic.switches'), ['switch_name' => 'topup_enabled', 'enabled' => 0])->assertSessionHasNoErrors();
        $this->assertFalse((bool) DB::table('magic_matrix_settings')->where('id', 1)->value('topup_enabled'));
        $member = $this->member();
        $this->actingAs($member)->post(route('admin.magic.switches'), ['switch_name' => 'settlement_enabled', 'enabled' => 1])->assertForbidden();
    }

    public function test_magic_team_is_only_rendered_on_its_menu_page(): void
    {
        $member = $this->member();
        $this->activate($member);
        $member->forceFill(['security_pin' => Hash::make('123456')])->save();
        $this->actingAs($member)->get(route('member.dashboard'))->assertOk()->assertDontSee('id="magic-team"', false)->assertDontSee('Your Magic Matrix Placement');
        $this->get(route('member.detail', ['panel' => 'magic-team']))->assertOk()->assertSee('Your Magic Matrix Placement')->assertSee('Members Below Your Position')->assertDontSee('Your position is shown separately.');
    }
}
