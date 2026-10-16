<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiLoginTest extends TestCase
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
            && ($cfg['database'] ?? '') === 'leobot_financial_disposable'
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
            || $identity->database_name !== 'leobot_financial_disposable'
            || (int) $identity->server_port !== 5433) {
            throw new \RuntimeException(
                'SAFETY STOP: Database identity mismatch.'
            );
        }

        $this->leobotOriginalRefreshDatabase();
    }

    private function createMember(string $status = 'inactive'): User
    {
        return User::create([
            'username' => 'TX654321',
            'sponsor_id' => null,
            'name' => 'API Login Test Member',
            'email' => 'api-login@example.test',
            'phone' => '9000000001',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => $status,
        ]);
    }

    public function test_inactive_member_can_login_with_read_only_token(): void
    {
        $member = $this->createMember('inactive');

        $response = $this->postJson('/api/login', [
            'login' => $member->username,
            'password' => 'TestPassword123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.status', 'inactive')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['access_token']);

        $token = PersonalAccessToken::query()
            ->where('tokenable_type', $member->getMorphClass())
            ->where('tokenable_id', $member->id)
            ->firstOrFail();

        $this->assertSame(['member:read'], $token->abilities);
        $this->assertSame(1, $member->tokens()->count());
    }

    public function test_active_member_can_login_with_read_only_token(): void
    {
        $member = $this->createMember('active');

        $response = $this->postJson('/api/login', [
            'login' => $member->email,
            'password' => 'TestPassword123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.status', 'active');

        $this->assertSame(
            ['member:read'],
            $member->tokens()->firstOrFail()->abilities
        );
    }

    public function test_wrong_password_does_not_create_token(): void
    {
        $member = $this->createMember();

        $response = $this->postJson('/api/login', [
            'login' => $member->username,
            'password' => 'WrongPassword123!',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);

        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_unknown_member_cannot_login(): void
    {
        $response = $this->postJson('/api/login', [
            'login' => 'TX000000',
            'password' => 'TestPassword123!',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }



    public function test_new_login_does_not_delete_existing_token(): void
    {
        $member = $this->createMember();

        $member->createToken('existing_device', ['member:read']);

        $response = $this->postJson('/api/login', [
            'login' => $member->username,
            'password' => 'TestPassword123!',
        ]);

        $response->assertOk();

        $this->assertSame(2, $member->tokens()->count());
    }
}
