<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(
        string $role,
        string $status = 'active'
    ): User {
        $id = DB::table('users')->insertGetId([
            'username' => 'DASH' . Str::upper(Str::random(10)),
            'name' => 'Dashboard Security Test',
            'email' => Str::uuid() . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => $status,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertForbidden();
    }

    public function test_member_cannot_access_dashboard(): void
    {
        $this->actingAs($this->createUser('member'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_inactive_admin_cannot_access_dashboard(): void
    {
        $this->actingAs($this->createUser('admin', 'inactive'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_inactive_super_admin_cannot_access_dashboard(): void
    {
        $this->actingAs($this->createUser('super_admin', 'inactive'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_active_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->createUser('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee('51-Level Commission');
    }

    public function test_active_super_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->createUser('super_admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Referral Privacy');
    }
}