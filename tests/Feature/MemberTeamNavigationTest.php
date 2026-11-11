<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class MemberTeamNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_downline_team_links_are_scoped_and_use_canonical_sponsor_ids(): void
    {
        $root = User::factory()->create(['role' => 'member', 'security_pin' => Hash::make('123456')]);
        $child = User::factory()->create(['role' => 'member', 'status' => 'active', 'sponsor_user_id' => $root->id, 'sponsor_id' => 'legacy-stale']);
        $grandchild = User::factory()->create(['role' => 'member', 'status' => 'inactive', 'sponsor_user_id' => $child->id]);
        $outsider = User::factory()->create(['role' => 'member']);
        $this->actingAs($root)->get(route('member.detail', ['panel' => 'direct-referrals']))->assertOk()->assertSee('Team View')->assertSee($child->username)->assertSee(route('member.team-levels', ['team_member' => $child->id]), false);
        $this->actingAs($root)->get(route('member.team-levels'))->assertOk()->assertSee($child->username)->assertSee('View Team');
        $this->get(route('member.team-levels', ['team_member' => $child->id]))->assertOk()->assertSee($grandchild->username)->assertDontSee($outsider->username);
        $this->get(route('member.team-levels', ['team_member' => $grandchild->id]))->assertOk()->assertSee('No members found on this level.');
        $this->get(route('member.team-levels', ['team_member' => $outsider->id]))->assertForbidden();
        $this->get(route('member.detail', ['panel' => 'team-breakdown']))->assertOk()->assertSee('Member ID')->assertSee('Member Name')->assertSee($child->username)->assertSee($child->name);
        $this->get(route('member.detail', ['panel' => 'team-breakdown', 'breakdown_level' => 2]))->assertOk()->assertSee($grandchild->username);
    }

    public function test_portal_session_cookie_names_are_separate(): void
    {
        $base = config('session.cookie');
        $this->get('/login')->assertOk()->assertCookie($base);
        $this->get('/admin/login')->assertOk()->assertCookie($base.'_admin');
        $this->assertSame($base, config('session.cookie'));
        $this->get('/login')->assertOk()->assertCookie($base);
    }
}
