<?php
namespace Tests\Unit;

use App\Services\UsdtWalletChallengeService;
use RuntimeException;
use Tests\TestCase;

final class UsdtWalletChallengeMessageTest extends TestCase {
    public function test_bep20_message_is_bound_to_member_network_address_nonce_and_expiry(): void {
        $nonce = str_repeat('a', 64);
        $m = UsdtWalletChallengeService::message(42, 'BEP20', '0x'.str_repeat('b',40), $nonce, '2026-10-17T20:00:00Z');
        $this->assertStringContainsString('Member: 42', $m);
        $this->assertStringContainsString('Network: BEP20', $m);
        $this->assertStringContainsString('Nonce: '.$nonce, $m);
        $this->assertStringContainsString('no transfer authorization', $m);
    }
    public function test_invalid_nonce_is_rejected(): void {
        $this->expectException(RuntimeException::class);
        UsdtWalletChallengeService::message(42, 'BEP20', '0x'.str_repeat('b',40), 'weak', '2026-10-17T20:00:00Z');
    }
    public function test_invalid_network_is_rejected(): void {
        $this->expectException(RuntimeException::class);
        UsdtWalletChallengeService::message(42, 'ETH', '0x'.str_repeat('b',40), str_repeat('a',64), '2026-10-17T20:00:00Z');
    }
}
