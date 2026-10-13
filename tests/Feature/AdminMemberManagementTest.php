<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(
        string $role = 'member',
        string $status = 'active',
        ?string $username = null,
        ?string $sponsorUserId = null
    ): User {
        $id = DB::table('users')->insertGetId([
            'username' => $username ?? 'MEM' . Str::upper(Str::random(10)),
            'name' => 'Member Test User',
            'email' => Str::uuid() . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => $status,
            'role' => $role,
            'sponsor_id' => $sponsorUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    public function test_guest_cannot_access_members(): void
    {
        $this->get('/admin/members')->assertForbidden();
    }

    public function test_member_cannot_access_members(): void
    {
        $this->actingAs($this->createUser())
            ->get('/admin/members')
            ->assertForbidden();
    }

    public function test_inactive_admin_cannot_access_members(): void
    {
        $this->actingAs($this->createUser('admin', 'inactive'))
            ->get('/admin/members')
            ->assertForbidden();
    }

    public function test_inactive_super_admin_cannot_access_members(): void
    {
        $this->actingAs($this->createUser('super_admin', 'inactive'))
            ->get('/admin/members')
            ->assertForbidden();
    }

    public function test_active_admin_can_view_members(): void
    {
        $admin = $this->createUser('admin');
        $this->createUser('member', 'active', 'TESTMEMBER123');

        $this->actingAs($admin)
            ->get('/admin/members')
            ->assertOk()
            ->assertSee('Member Management')
            ->assertSee('TESTMEMBER123');
    }

    public function test_active_super_admin_can_view_members(): void
    {
        $this->actingAs($this->createUser('super_admin'))
            ->get('/admin/members')
            ->assertOk();
    }

    public function test_username_search_filters_members(): void
    {
        $admin = $this->createUser('admin');

        $this->createUser('member', 'active', 'SEARCHALPHA123');
        $this->createUser('member', 'active', 'SEARCHBETA456');

        $this->actingAs($admin)
            ->get('/admin/members?search=SEARCHALPHA')
            ->assertOk()
            ->assertSee('SEARCHALPHA123')
            ->assertDontSee('SEARCHBETA456');
    }

    public function test_sensitive_credentials_are_not_displayed(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->get('/admin/members')
            ->assertOk()
            ->assertDontSee('TestPassword123!')
            ->assertDontSee('security_pin');
    }

    public function test_guest_cannot_view_member_details(): void
    {
        $member = $this->createUser();

        $this->get(route('admin.members.show', $member->id))
            ->assertForbidden();
    }

    public function test_regular_member_cannot_view_member_details(): void
    {
        $member = $this->createUser();

        $this->actingAs($this->createUser())
            ->get(route('admin.members.show', $member->id))
            ->assertForbidden();
    }

    public function test_inactive_admin_cannot_view_member_details(): void
    {
        $member = $this->createUser();

        $this->actingAs($this->createUser('admin', 'inactive'))
            ->get(route('admin.members.show', $member->id))
            ->assertForbidden();
    }

    public function test_active_admin_can_view_member_details(): void
    {
        $admin = $this->createUser('admin');
        $sponsor = $this->createUser('member', 'active', 'SPONSOR123');
        $member = $this->createUser('member', 'active', 'DETAILMEMBER123');

        DB::table('users')
            ->where('id', $member->id)
            ->update(['sponsor_user_id' => $sponsor->id]);

        DB::table('users')->insert([
            [
                'username' => 'DIRECTREF001',
                'name' => 'Direct Referral One',
                'email' => 'direct001@example.test',
                'phone' => '9000000001',
                'password' => Hash::make('TestPassword123!'),
                'status' => 'active',
                'role' => 'member',
                'sponsor_user_id' => $member->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'DIRECTREF002',
                'name' => 'Direct Referral Two',
                'email' => 'direct002@example.test',
                'phone' => '9000000002',
                'password' => Hash::make('TestPassword123!'),
                'status' => 'active',
                'role' => 'member',
                'sponsor_user_id' => $member->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.members.show', $member->id))
            ->assertOk()
            ->assertSee('Member Details')
            ->assertSee('DETAILMEMBER123')
            ->assertSee('SPONSOR123')
            ->assertSee('Direct Referrals')
            ->assertSee('2');
    }

    public function test_missing_member_details_returns_404(): void
    {
        $this->actingAs($this->createUser('admin'))
            ->get('/admin/members/999999999')
            ->assertNotFound();
    }

    public function test_member_details_do_not_expose_credentials(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser();

        $this->actingAs($admin)
            ->get(route('admin.members.show', $member->id))
            ->assertOk()
            ->assertDontSee($member->password)
            ->assertDontSee('security_pin')
            ->assertDontSee('TestPassword123!');
    }

    public function test_member_list_pagination(): void
    {
        $admin = $this->createUser('admin');

        for ($i = 1; $i <= 25; $i++) {
            $this->createUser(
                'member',
                'active',
                sprintf('PAGEUSER%03d', $i)
            );
        }

        $this->actingAs($admin)
            ->get('/admin/members')
            ->assertOk()
            ->assertSee('PAGEUSER025')
            ->assertDontSee('PAGEUSER005');

        $this->actingAs($admin)
            ->get('/admin/members?page=2')
            ->assertOk()
            ->assertSee('PAGEUSER005')
            ->assertDontSee('PAGEUSER025');
    }

    public function test_search_percent_is_literal(): void
    {
        $admin = $this->createUser('admin');

        $this->createUser('member', 'active', 'PERCENT%USER');
        $this->createUser('member', 'active', 'PERCENTXUSER');

        $this->actingAs($admin)
            ->get('/admin/members?search=' . urlencode('PERCENT%'))
            ->assertOk()
            ->assertSee('PERCENT%USER')
            ->assertDontSee('PERCENTXUSER');
    }

    public function test_search_underscore_is_literal(): void
    {
        $admin = $this->createUser('admin');

        $this->createUser('member', 'active', 'UNDER_SCORE');
        $this->createUser('member', 'active', 'UNDERXSCORE');

        $this->actingAs($admin)
            ->get('/admin/members?search=' . urlencode('UNDER_'))
            ->assertOk()
            ->assertSee('UNDER_SCORE')
            ->assertDontSee('UNDERXSCORE');
    }
}