<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeamCounterService;
use App\Services\TeamLevelViewerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SponsorReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private function member(?User $parent = null, string $status = 'inactive'): User
    {
        return User::factory()->create(['role' => 'member', 'status' => $status, 'sponsor_user_id' => $parent?->id, 'sponsor_id' => $parent?->username]);
    }

    public function test_subtree_move_updates_both_sponsor_fields_and_counts_once_without_financial_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $old = $this->member();
        $new = $this->member();
        $child = $this->member($old, 'active');
        $leaf = $this->member($child);
        DB::table('activation_packages')->insert(['user_id' => $child->id, 'activation_key' => 'sponsor-test-package', 'investment_amount' => '50.12345678', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$child, $leaf] as $member) {
            TeamCounterService::synchronize($member->id);
        }
        $money = DB::table('users')->orderBy('id')->get(['id', 'activation_balance', 'available_balance', 'total_investment'])->toJson();
        $ledger = DB::table('financial_ledger')->get()->toJson();
        $matrix = DB::table('magic_matrix_positions')->get()->toJson();
        $result = TeamCounterService::reassignSponsor($admin->id, $child->id, $new->id);
        $this->assertTrue($result['changed']);
        $this->assertSame(2, $result['subtree_members']);
        $this->assertDatabaseHas('users', ['id' => $child->id, 'sponsor_id' => $new->username, 'sponsor_user_id' => $new->id]);
        $this->assertDatabaseHas('users', ['id' => $leaf->id, 'sponsor_user_id' => $child->id]);
        $this->assertDatabaseHas('member_team_states', ['user_id' => $child->id, 'sponsor_user_id' => $new->id]);
        $this->assertDatabaseHas('member_teams', ['user_id' => $old->id, 'level' => 1, 'active_count' => 0]);
        $this->assertDatabaseHas('member_teams', ['user_id' => $new->id, 'level' => 1, 'active_count' => 1]);
        $this->assertDatabaseHas('member_teams', ['user_id' => $new->id, 'level' => 2, 'inactive_count' => 1]);
        $this->assertSame(0, bccomp('50.12345678', (string) DB::table('member_teams')->where('user_id', $new->id)->where('level', 1)->value('total_business'), 8));
        $this->assertSame(0, bccomp('0', (string) DB::table('member_teams')->where('user_id', $old->id)->where('level', 1)->value('total_business'), 8));
        $this->assertTrue(TeamLevelViewerService::containsMember($new->id, $leaf->id));
        $this->assertFalse(TeamLevelViewerService::containsMember($old->id, $leaf->id));
        $this->assertFalse(TeamCounterService::reassignSponsor($admin->id, $child->id, $new->id)['changed']);
        TeamCounterService::synchronize($leaf->id);
        $this->assertSame(1, DB::table('member_security_audits')->where('action', 'referral_sponsor_reassigned')->count());
        $this->assertSame($money, DB::table('users')->orderBy('id')->get(['id', 'activation_balance', 'available_balance', 'total_investment'])->toJson());
        $this->assertSame($ledger, DB::table('financial_ledger')->get()->toJson());
        $this->assertSame($matrix, DB::table('magic_matrix_positions')->get()->toJson());
    }

    public function test_cyclic_move_is_rejected_without_writes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $root = $this->member();
        $leaf = $this->member($root);
        try {
            TeamCounterService::reassignSponsor($admin->id, $root->id, $leaf->id);
            $this->fail('Cycle allowed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cycle', $e->getMessage());
        }
        $this->assertNull($root->fresh()->sponsor_user_id);
        $this->assertDatabaseCount('member_security_audits', 0);
    }

    public function test_non_admin_cannot_move_members(): void
    {
        $member = $this->member();
        $parent = $this->member();
        $this->expectException(RuntimeException::class);
        TeamCounterService::reassignSponsor($parent->id, $member->id, $parent->id);
    }

    public function test_inconsistent_counter_rolls_back_entire_move(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $old = $this->member();
        $new = $this->member();
        $child = $this->member($old);
        TeamCounterService::synchronize($child->id);
        DB::table('member_teams')->where('user_id', $old->id)->update(['inactive_count' => 0]);
        try {
            TeamCounterService::reassignSponsor($admin->id, $child->id, $new->id);
            $this->fail('Corruption ignored');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('integrity', $e->getMessage());
        }
        $this->assertSame($old->id, $child->fresh()->sponsor_user_id);
        $this->assertDatabaseHas('member_team_states', ['user_id' => $child->id, 'sponsor_user_id' => $old->id]);
        $this->assertDatabaseCount('member_security_audits', 0);
    }

    public function test_beta_only_command_refuses_other_environments(): void
    {
        $this->artisan('lumiflowon:reassign-sponsor', ['member' => 'TX766610', 'sponsor' => 'TX659053', '--beta-if-present' => true, '--apply' => true])->assertFailed();
        $this->assertDatabaseCount('member_security_audits', 0);
    }
}
