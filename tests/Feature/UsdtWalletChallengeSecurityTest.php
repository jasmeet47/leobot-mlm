<?php

namespace Tests\Feature;

use App\Contracts\UsdtWalletOwnershipVerifier;
use App\Services\UsdtWalletChallengeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsdtWalletChallengeSecurityTest extends TestCase
{
    use RefreshDatabase;
    private const ADDRESS = '0x1111111111111111111111111111111111111111';

    private function service(bool $valid): UsdtWalletChallengeService
    {
        $verifier = new class($valid) implements UsdtWalletOwnershipVerifier {
            public function __construct(private bool $valid) {}

            public function verify(
                string $network,
                string $address,
                string $message,
                string $signature
            ): bool {
                return $this->valid && $signature === 'test-signature';
            }
        };

        return new UsdtWalletChallengeService($verifier);
    }

    private function challenge(): array
    {
        $userId = \App\Models\User::factory()->create()->id;

        $this->assertGreaterThan(0, $userId, 'Disposable DB needs a test user.');

        $issued = $this->service(false)->issue(
            $userId,
            'BEP20',
            self::ADDRESS
        );

        return [$userId, $issued];
    }

    public function test_valid_signature_consumes_challenge_once(): void
    {
        [$userId, $issued] = $this->challenge();

        $service = $this->service(true);

        $this->assertTrue($service->consume(
            $userId,
            $issued['challenge_id'],
            $issued['message'],
            'test-signature'
        ));

        $this->assertFalse($service->consume(
            $userId,
            $issued['challenge_id'],
            $issued['message'],
            'test-signature'
        ));
    }

    public function test_invalid_signature_does_not_consume(): void
    {
        [$userId, $issued] = $this->challenge();

        $this->assertFalse($this->service(false)->consume(
            $userId,
            $issued['challenge_id'],
            $issued['message'],
            'test-signature'
        ));

        $this->assertNull(
            DB::table('usdt_wallet_challenges')
                ->where('id', $issued['challenge_id'])
                ->value('consumed_at')
        );
    }

    public function test_expired_challenge_is_rejected(): void
    {
        [$userId, $issued] = $this->challenge();

        DB::table('usdt_wallet_challenges')
            ->where('id', $issued['challenge_id'])
            ->update(['expires_at' => now('UTC')->subMinute()]);

        $this->assertFalse($this->service(true)->consume(
            $userId,
            $issued['challenge_id'],
            $issued['message'],
            'test-signature'
        ));
    }

    public function test_wrong_member_is_rejected(): void
    {
        [$userId, $issued] = $this->challenge();

        $this->assertFalse($this->service(true)->consume(
            $userId + 999999,
            $issued['challenge_id'],
            $issued['message'],
            'test-signature'
        ));
    }

    public function test_tampered_network_message_is_rejected(): void
    {
        [$userId, $issued] = $this->challenge();

        $tampered = str_replace(
            'Network: BEP20',
            'Network: TRC20',
            $issued['message']
        );

        $this->assertFalse($this->service(true)->consume(
            $userId,
            $issued['challenge_id'],
            $tampered,
            'test-signature'
        ));
    }
}
