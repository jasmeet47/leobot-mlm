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
}