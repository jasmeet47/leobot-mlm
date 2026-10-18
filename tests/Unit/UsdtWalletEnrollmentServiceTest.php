<?php

namespace Tests\Unit;

use App\Contracts\UsdtWalletOwnershipVerifier;
use App\Services\UsdtWalletChallengeService;
use App\Services\UsdtWalletEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class UsdtWalletEnrollmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = '0x1111111111111111111111111111111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            && config('database.default') !== 'pgsql') {
            $this->fail('Unexpected test database driver.');
        }

        if (!app()->environment('testing')) {
            $this->fail('Wallet enrollment tests require testing environment.');
        }
    }

    private function memberId(): int
    {
        return (int) DB::table('users')->insertGetId([
            'username' => 'wallet_' . bin2hex(random_bytes(6)),
            'phone' => '9' . (string) random_int(100000000, 999999999),
            'name' => 'Wallet Test Member',
            'email' => 'wallet-enroll-' . bin2hex(random_bytes(5)) . '@example.test',
            'password' => bcrypt('testing-only-password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_valid_signature_enrolls_wallet_and_replay_is_rejected(): void
    {
        $this->app->instance(
            UsdtWalletOwnershipVerifier::class,
            new class implements UsdtWalletOwnershipVerifier {
                public function verify(
                    string $network,
                    string $address,
                    string $message,
                    string $signature
                ): bool {
                    return $network === 'BEP20'
                        && $address === '0x1111111111111111111111111111111111111111'
                        && $signature === 'valid-test-signature';
                }
            }
        );

        $memberId = $this->memberId();
        $challenges = app(UsdtWalletChallengeService::class);
        $challenge = $challenges->issue($memberId, 'BEP20', self::ADDRESS);

        $service = app(UsdtWalletEnrollmentService::class);

        $walletId = $service->enroll(
            $memberId,
            $challenge['challenge_id'],
            $challenge['message'],
            'valid-test-signature'
        );

        $this->assertGreaterThan(0, $walletId);

        $wallet = DB::table('usdt_member_wallets')->find($walletId);
        $this->assertSame($memberId, (int) $wallet->user_id);
        $this->assertSame('BEP20', $wallet->network);
        $this->assertNotNull($wallet->verified_at);

        try {
            $service->enroll(
                $memberId,
                $challenge['challenge_id'],
                $challenge['message'],
                'valid-test-signature'
            );
            $this->fail('Replay should have been rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('consumed', $e->getMessage());
        }

        $this->assertSame(
            1,
            DB::table('usdt_member_wallets')->where('user_id', $memberId)->count()
        );
    }

    public function test_invalid_signature_does_not_enroll_wallet(): void
    {
        $this->app->instance(
            UsdtWalletOwnershipVerifier::class,
            new class implements UsdtWalletOwnershipVerifier {
                public function verify(
                    string $network,
                    string $address,
                    string $message,
                    string $signature
                ): bool {
                    return false;
                }
            }
        );

        $memberId = $this->memberId();
        $challenge = app(UsdtWalletChallengeService::class)
            ->issue($memberId, 'BEP20', self::ADDRESS);

        try {
            app(UsdtWalletEnrollmentService::class)->enroll(
                $memberId,
                $challenge['challenge_id'],
                $challenge['message'],
                'invalid-signature'
            );
            $this->fail('Invalid signature must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('verification failed', $e->getMessage());
        }

        $this->assertSame(0, DB::table('usdt_member_wallets')->count());
        $this->assertNull(
            DB::table('usdt_wallet_challenges')
                ->where('id', $challenge['challenge_id'])
                ->value('consumed_at')
        );
    }
}