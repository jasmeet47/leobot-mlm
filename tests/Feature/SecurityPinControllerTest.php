<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityPinControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
     * Create a test member.
     *
     * All database changes use the temporary
     * SQLite testing database.
     */
    private function createMember(
        ?string $pin = null
    ): User {
        $unique = str_replace(
            '-',
            '',
            (string) Str::uuid()
        );

        $id = DB::table('users')->insertGetId([
            'username' =>
                'TX' . substr($unique, 0, 12),

            'name' =>
                'Security PIN Test Member',

            'email' =>
                $unique . '@example.test',

            'phone' =>
                '9000000000',

            'password' =>
                Hash::make('TestPassword123!'),

            'security_pin' =>
                $pin === null
                    ? null
                    : Hash::make($pin),

            'status' => 'inactive',

            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $member = User::findOrFail($id);

        RateLimiter::clear(
            'security-pin:update:' . $member->id
        );

        return $member;
    }

    private function validData(
        string $pin = '123456'
    ): array {
        return [
            'current_password' =>
                'TestPassword123!',

            'security_pin' => $pin,

            'security_pin_confirmation' => $pin,
        ];
    }

    /*
     * TEST 1
     * Correct password sets a hashed PIN.
     */
    public function test_member_can_set_hashed_pin(): void
    {
        $member = $this->createMember();

        $response = $this
            ->actingAs($member)
            ->from('/join')
            ->post(
                '/security-pin',
                $this->validData()
            );

        $response->assertRedirect('/join');

        $response->assertSessionHas(
            'success',
            'Security PIN saved successfully.'
        );

        $storedPin = (string) $member
            ->fresh()
            ->security_pin;

        $this->assertNotSame(
            '123456',
            $storedPin
        );

        $this->assertTrue(
            Hash::check('123456', $storedPin)
        );
    }

    /*
     * TEST 2
     * Incorrect account password is rejected.
     */
    public function test_wrong_password_is_rejected(): void
    {
        $member = $this->createMember();

        $data = $this->validData();

        $data['current_password'] =
            'WrongPassword123!';

        $response = $this
            ->actingAs($member)
            ->from('/join')
            ->post('/security-pin', $data);

        $response->assertSessionHasErrors(
            'current_password'
        );

        $this->assertNull(
            $member->fresh()->security_pin
        );
    }

    /*
     * TEST 3
     * PIN must contain exactly 6 digits.
     */
    public function test_invalid_pin_is_rejected(): void
    {
        $member = $this->createMember();

        $response = $this
            ->actingAs($member)
            ->from('/join')
            ->post(
                '/security-pin',
                $this->validData('12345')
            );

        $response->assertSessionHasErrors(
            'security_pin'
        );

        $this->assertNull(
            $member->fresh()->security_pin
        );
    }

    /*
     * TEST 4
     * PIN confirmation must match.
     */
    public function test_pin_confirmation_must_match(): void
    {
        $member = $this->createMember();

        $data = $this->validData();

        $data['security_pin_confirmation'] =
            '654321';

        $response = $this
            ->actingAs($member)
            ->from('/join')
            ->post('/security-pin', $data);

        $response->assertSessionHasErrors(
            'security_pin'
        );

        $this->assertNull(
            $member->fresh()->security_pin
        );
    }

    /*
     * TEST 5
     * Leading zero is allowed.
     */
    public function test_pin_can_start_with_zero(): void
    {
        $member = $this->createMember();

        $response = $this
            ->actingAs($member)
            ->from('/join')
            ->post(
                '/security-pin',
                $this->validData('012345')
            );

        $response->assertSessionHasNoErrors();

        $this->assertTrue(
            Hash::check(
                '012345',
                (string) $member
                    ->fresh()
                    ->security_pin
            )
        );
    }

    /*
     * TEST 6
     * Five failed attempts trigger rate limiting.
     */
    public function test_repeated_failures_are_blocked(): void
    {
        $member = $this->createMember();

        $wrongData = $this->validData();

        $wrongData['current_password'] =
            'WrongPassword123!';

        for ($i = 0; $i < 5; $i++) {
            $this
                ->actingAs($member)
                ->from('/join')
                ->post(
                    '/security-pin',
                    $wrongData
                )
                ->assertSessionHasErrors(
                    'current_password'
                );
        }

        /*
         * Even a correct password is blocked
         * temporarily after five failures.
         */
        $response = $this
            ->actingAs($member)
            ->from('/join')
            ->post(
                '/security-pin',
                $this->validData()
            );

        $response->assertSessionHasErrors(
            'security_pin'
        );

        $this->assertNull(
            $member->fresh()->security_pin
        );
    }

    /*
     * TEST 7
     * Member cannot change another user's PIN.
     */
    public function test_only_logged_in_members_pin_changes(): void
    {
        $firstMember = $this->createMember();

        $secondMember = $this->createMember(
            '654321'
        );

        $secondOriginalPin = (string)
            $secondMember->security_pin;

        $response = $this
            ->actingAs($firstMember)
            ->from('/join')
            ->post(
                '/security-pin',
                array_merge(
                    $this->validData(),
                    [
                        'user_id' =>
                            $secondMember->id,
                    ]
                )
            );

        $response->assertSessionHasNoErrors();

        $this->assertTrue(
            Hash::check(
                '123456',
                (string) $firstMember
                    ->fresh()
                    ->security_pin
            )
        );

        $this->assertSame(
            $secondOriginalPin,
            (string) $secondMember
                ->fresh()
                ->security_pin
        );
    }

    /*
     * TEST 8
     * Guest cannot submit a PIN.
     */
    public function test_guest_cannot_set_pin(): void
    {
        $response = $this->postJson(
            '/security-pin',
            $this->validData()
        );

        $response->assertUnauthorized();
    }

    /*
     * TEST 9 - NEW
     * Guest opening the PIN page must
     * be redirected to /join.
     */
    public function test_guest_is_redirected_to_join(): void
    {
        $response = $this->get('/security-pin');

        $response->assertRedirect('/join');
    }

    /*
     * TEST 10 - NEW
     * Authenticated member can open
     * the Security PIN page.
     */
    public function test_member_can_open_security_pin_page(): void
    {
        $member = $this->createMember();

        $response = $this
            ->actingAs($member)
            ->get('/security-pin');

        $response->assertOk();

        $response->assertViewIs(
            'security-pin'
        );

        $response->assertSee(
            'Save Security PIN'
        );

        $response->assertSee(
            'security_pin_confirmation'
        );
    }
}
