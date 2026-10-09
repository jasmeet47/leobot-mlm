<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberLoginTest extends TestCase
{
    use RefreshDatabase;

    /*
     * Create a test member in temporary SQLite.
     * No real member or live database is changed.
     */
    private function createMember(): User
    {
        $id = DB::table('users')->insertGetId([
            'username' => 'TX987654',
            'name' => 'Login Test Member',
            'email' => 'login-test@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    /*
     * TEST 1
     * Correct username and password:
     * Member should log in and open Security PIN.
     */
    public function test_member_can_login_with_correct_password(): void
    {
        $member = $this->createMember();

        $response = $this->post('/login', [
            'username' => 'TX987654',
            'password' => 'TestPassword123!',
        ]);

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('security-pin.form')
        );

        $this->assertAuthenticatedAs($member);
    }

    /*
     * TEST 2
     * Wrong password must reject login.
     */
    public function test_wrong_password_cannot_login(): void
    {
        $this->createMember();

        $response = $this
            ->from('/join')
            ->post('/login', [
                'username' => 'TX987654',
                'password' => 'WrongPassword123!',
            ]);

        $response->assertRedirect('/join');

        $response->assertSessionHasErrors(
            'username'
        );

        $this->assertGuest();
    }

    /*
     * TEST 3
     * Unknown member username must reject login.
     */
    public function test_unknown_username_cannot_login(): void
    {
        $response = $this
            ->from('/join')
            ->post('/login', [
                'username' => 'TX000000',
                'password' => 'TestPassword123!',
            ]);

        $response->assertRedirect('/join');

        $response->assertSessionHasErrors(
            'username'
        );

        $this->assertGuest();
    }
}
