<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBlockedAccessSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_admin_and_super_admin_cannot_access_admin_dashboard(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $id = DB::table('users')->insertGetId([
                'username' => 'BLOCK' . Str::upper(Str::random(12)),
                'name' => 'Blocked Administrator',
                'email' => Str::uuid() . '@example.test',
                'phone' => '9000000000',
                'password' => Hash::make('TestPassword123!'),
                'security_pin' => null,
                'status' => 'active',
                'role' => $role,
                'is_blocked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs(User::findOrFail($id))
                ->get('/admin/dashboard')
                ->assertForbidden();
        }
    }
}
