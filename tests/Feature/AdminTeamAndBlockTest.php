<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTeamAndBlockTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'member', string $status = 'active', ?string $username = null, ?User $sponsor = null): User
    {
        $id = DB::table('users')->insertGetId([
            'username' => $username ?? ('USR' . Str::upper(Str::random(10))),
            'name' => 'Test Account',
            'email' => Str::uuid() . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'role' => $role,
            'status' => $status,
            'sponsor_user_id' => $sponsor?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return User::findOrFail($id);
    }

    public function test_guest_and_regular_member_cannot_view_51_level_team(): void
    {
        $member = $this->makeUser();
        $url = route('admin.members.team-levels', $member->id);
        $this->get($url)->assertForbidden();
        $this->actingAs($this->makeUser())->get($url)->assertForbidden();
        $this->actingAs($this->makeUser('admin', 'inactive'))->get($url)->assertForbidden();
    }

    public function test_admin_and_superadmin_can_view_team_levels_without_other_branches(): void
    {
        $root = $this->makeUser('member', 'active', 'ROOTVIEW');
        $one = $this->makeUser('member', 'active', 'LEVELONE', $root);
        $this->makeUser('member', 'active', 'LEVELTWO', $one);
        $other = $this->makeUser('member', 'active', 'UNRELATED');
        $this->makeUser('member', 'active', 'OTHERBRANCH', $other);
        $url = route('admin.members.team-levels', $root->id);

        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->get($url . '?level=2')->assertOk()
                ->assertSee('Level 1: 1')
                ->assertSee('Level 2: 1')
                ->assertSee('LEVELTWO')
                ->assertDontSee('OTHERBRANCH')
                ->assertDontSee('LEVELONE</td>', false);
        }
    }

    public function test_level_51_is_included_but_level_52_is_not(): void
    {
        $root = $this->makeUser('member', 'active', 'CHAINROOT');
        $parent = $root;
        for ($i = 1; $i <= 52; $i++) {
            $parent = $this->makeUser('member', 'active', sprintf('DEPTH%03d', $i), $parent);
        }
        $this->actingAs($this->makeUser('admin'))
            ->get(route('admin.members.team-levels', $root->id) . '?level=51')
            ->assertOk()->assertSee('DEPTH051')->assertDontSee('DEPTH052');
    }

    public function test_team_viewer_search_treats_percent_and_underscore_literally(): void
    {
        $root = $this->makeUser();
        $this->makeUser('member', 'active', 'TEAMA%ONE', $root);
        $this->makeUser('member', 'active', 'TEAMAXONE', $root);
        $this->makeUser('member', 'active', 'TEAMB_TWO', $root);
        $this->makeUser('member', 'active', 'TEAMBXTWO', $root);
        $url = route('admin.members.team-levels', $root->id) . '?level=1&search=';
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->get($url . urlencode('TEAMA%'))
            ->assertOk()->assertSee('TEAMA%ONE')->assertDontSee('TEAMAXONE');
        $this->actingAs($admin)->get($url . urlencode('TEAMB_'))
            ->assertOk()->assertSee('TEAMB_TWO')->assertDontSee('TEAMBXTWO');
    }

    public function test_team_viewer_uses_pagination_and_validates_inputs(): void
    {
        $root = $this->makeUser();
        for ($i = 1; $i <= 25; $i++) {
            $this->makeUser('member', 'active', sprintf('LVLPAGE%03d', $i), $root);
        }
        $url = route('admin.members.team-levels', $root->id);
        $this->actingAs($this->makeUser('admin'))
            ->get($url)->assertOk()->assertSee('LVLPAGE025')->assertDontSee('LVLPAGE005');
        $this->get($url . '?page=2')->assertOk()->assertSee('LVLPAGE005')->assertDontSee('LVLPAGE025');
        $this->getJson($url . '?level=52')->assertStatus(422)->assertJsonValidationErrors('level');
        $this->getJson($url . '?search=' . str_repeat('A', 101))
            ->assertStatus(422)->assertJsonValidationErrors('search');
    }

    public function test_missing_member_team_returns_404_and_no_credentials_exposed(): void
    {
        $admin = $this->makeUser('admin');
        $root = $this->makeUser();
        $member = $this->makeUser('member', 'active', 'VISIBLETEAM', $root);
        $this->actingAs($admin)
            ->get('/admin/members/999999999/team-levels')->assertNotFound();
        $this->get(route('admin.members.team-levels', $root->id))
            ->assertOk()->assertSee($member->username)
            ->assertDontSee($member->password)->assertDontSee('security_pin');
    }

    public function test_guest_regular_member_and_inactive_admin_cannot_block(): void
    {
        $target = $this->makeUser();
        $url = route('admin.members.block', $target->id);
        $this->post($url, ['reason' => 'Review required'])->assertForbidden();
        $this->actingAs($this->makeUser())->post($url, ['reason' => 'Review required'])->assertForbidden();
        $this->actingAs($this->makeUser('admin', 'inactive'))
            ->post($url, ['reason' => 'Review required'])->assertForbidden();
        $this->assertSame('active', $target->fresh()->status);
        $this->assertDatabaseCount('member_account_block_audits', 0);
    }

    public function test_admin_cannot_block_another_admin_or_superadmin(): void
    {
        $admin = $this->makeUser('admin');
        foreach (['admin', 'super_admin'] as $role) {
            $target = $this->makeUser($role);
            $this->actingAs($admin)
                ->post(route('admin.members.block', $target->id), ['reason' => 'Not allowed'])
                ->assertForbidden();
            $this->assertSame('active', $target->fresh()->status);
        }
        $this->assertDatabaseCount('member_account_block_audits', 0);
    }

    public function test_block_requires_reason_and_does_not_mutate(): void
    {
        $target = $this->makeUser();
        $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.members.block', $target->id), ['reason' => 'x'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('active', $target->fresh()->status);
        $this->assertDatabaseCount('member_account_block_audits', 0);
    }

    public function test_block_revokes_all_target_tokens_and_preserves_team_and_wallet_fields(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser('member', 'active', 'FREEZETARGET');
        $child = $this->makeUser('member', 'active', 'FREEZECHILD', $target);
        $target->createToken('device1', ['member:read']);
        $target->createToken('device2', ['member:read']);
        $admin->createToken('admin-device', ['member:read']);

        $this->actingAs($admin)
            ->post(route('admin.members.block', $target->id), ['reason' => 'Suspicious account activity'])
            ->assertRedirect(route('admin.members.show', $target->id));

        $this->assertSame('active', $target->fresh()->status);
        $this->assertTrue($target->fresh()->is_blocked);
        $this->assertSame(1, (int) $target->fresh()->member_login_version);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(1, $admin->tokens()->count());
        $this->assertSame($target->id, $child->fresh()->sponsor_user_id);
        $this->assertDatabaseHas('member_account_block_audits', [
            'member_user_id' => $target->id,
            'admin_user_id' => $admin->id,
            'action' => 'blocked',
            'previous_status' => 'active',
            'new_status' => 'blocked',
        ]);
    }

    public function test_blocked_member_cannot_log_in_using_web_or_api(): void
    {
        $target = $this->makeUser('member', 'active', 'BLOCKEDLOGIN');
        DB::table('users')->where('id', $target->id)->update(['is_blocked' => true]);
        $this->from('/join')->post('/login', [
            'username' => $target->username, 'password' => 'TestPassword123!',
        ])->assertRedirect('/join')->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->postJson('/api/login', [
            'login' => $target->username, 'password' => 'TestPassword123!',
        ])->assertForbidden();
        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_blocked_member_with_existing_session_cannot_access_pin_page(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser();
        $this->actingAs($admin)->post(route('admin.members.block', $target->id),
            ['reason' => 'Account access suspended'])->assertRedirect();
        $this->actingAs($target->fresh())->get(route('security-pin.form'))->assertForbidden();
    }

    public function test_existing_web_session_is_rejected_when_blocked_and_after_unblock(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser();
        $this->actingAs($admin)
            ->post(route('admin.members.block', $target->id), ['reason' => 'Account review required'])
            ->assertRedirect();
        $this->post(route('admin.members.unblock', $target->id), ['reason' => 'Review now complete'])
            ->assertRedirect();
        $this->assertSame('active', $target->fresh()->status);
        $this->assertFalse($target->fresh()->is_blocked);
        $this->withSession(['member_login_version' => 0])->actingAs($target->fresh())->get(route('security-pin.form'))->assertForbidden();
    }

    public function test_unblock_restores_inactive_member_and_logs_both_actions(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser('member', 'inactive', 'INACTIVEFREEZE');
        $this->actingAs($admin)->post(route('admin.members.block', $target->id),
            ['reason' => 'Account check in progress'])->assertRedirect();
        $this->post(route('admin.members.unblock', $target->id),
            ['reason' => 'Account cleared after review'])->assertRedirect();
        $this->assertSame('inactive', $target->fresh()->status);
        $this->assertFalse($target->fresh()->is_blocked);
        $this->assertSame(2, DB::table('member_account_block_audits')
            ->where('member_user_id', $target->id)->count());
        $this->actingAs($admin)->get(route('admin.members.show', $target->id))
            ->assertOk()->assertSee('Account check in progress')
            ->assertSee('Account cleared after review');
    }

    public function test_duplicate_block_and_unblock_fail_without_extra_audits(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser();
        $this->actingAs($admin)->post(route('admin.members.block', $target->id),
            ['reason' => 'Temporary account review'])->assertRedirect();
        $this->post(route('admin.members.block', $target->id),
            ['reason' => 'Repeat attempt'])->assertStatus(409);
        $this->post(route('admin.members.unblock', $target->id),
            ['reason' => 'Review completed'])->assertRedirect();
        $this->post(route('admin.members.unblock', $target->id),
            ['reason' => 'Repeat attempt'])->assertStatus(409);
        $this->assertDatabaseCount('member_account_block_audits', 2);
    }

    public function test_admin_can_still_view_blocked_member_and_direct_referrals(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser('member', 'active');
        DB::table('users')->where('id', $target->id)->update(['is_blocked' => true]);
        $this->makeUser('member', 'active', 'STILLREFERRAL', $target);
        $this->actingAs($admin)
            ->get(route('admin.members.show', $target->id))->assertOk()
            ->assertSee('Unblock Member ID');
        $this->get(route('admin.members.referrals', $target->id))
            ->assertOk()->assertSee('STILLREFERRAL');
    }
}
