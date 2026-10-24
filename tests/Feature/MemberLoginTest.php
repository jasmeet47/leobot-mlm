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
    use RefreshDatabase {
        refreshDatabase as private leobotOriginalRefreshDatabase;
    }

    public function refreshDatabase(): void
    {
        $name = config('database.default');
        $cfg = config('database.connections.' . $name, []);

        $safe = app()->environment('testing')
            && $name === 'pgsql'
            && ($cfg['host'] ?? '') === '127.0.0.1'
            && (string) ($cfg['port'] ?? '') === '5433'
            && ($cfg['database'] ?? '') === 'lumiflowon_final_regression_20261024'
            && empty($cfg['url']);

        if (!$safe) {
            throw new \RuntimeException(
                'SAFETY STOP: Disposable PostgreSQL required.'
            );
        }

        $identity = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT current_database() AS database_name, inet_server_port() AS server_port'
        );

        if (!$identity
            || $identity->database_name !== 'lumiflowon_final_regression_20261024'
            || (int) $identity->server_port !== 5433) {
            throw new \RuntimeException(
                'SAFETY STOP: Database identity mismatch.'
            );
        }

        $this->leobotOriginalRefreshDatabase();
    }

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
            'phone' => (string) random_int(1000000000, 9999999999),
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

        $response = $this->withSession(['lb_login_captcha' => ['a' => 2, 'b' => 3, 'answer' => 5]])->post('/login', ['captcha_answer' => '5',
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
            ->withSession(['lb_login_captcha' => ['a' => 2, 'b' => 3, 'answer' => 5]])->post('/login', ['captcha_answer' => '5',
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
            ->withSession(['lb_login_captcha' => ['a' => 2, 'b' => 3, 'answer' => 5]])->post('/login', ['captcha_answer' => '5',
                'username' => 'TX000000',
                'password' => 'TestPassword123!',
            ]);

        $response->assertRedirect('/join');

        $response->assertSessionHasErrors(
            'username'
        );

        $this->assertGuest();
    }
    public function test_blocked_active_member_cannot_create_web_session(): void
    {
        $member = $this->createMember();
        DB::table('users')->where('id', $member->id)->update(['status' => 'active', 'is_blocked' => true]);

        $this->from('/join')->withSession(['lb_login_captcha' => ['a' => 2, 'b' => 3, 'answer' => 5]])->post('/login', ['captcha_answer' => '5',
            'username' => $member->username,
            'password' => 'TestPassword123!',
        ])->assertRedirect('/join')->assertSessionHasErrors('username');

        $this->assertGuest();
    }


}
