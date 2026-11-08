<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActivationService;
use App\Services\MagicMatrixEnrollmentService;
use App\Services\MagicMatrixTeamReadService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
