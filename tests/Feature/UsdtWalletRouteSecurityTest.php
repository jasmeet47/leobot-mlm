<?php

namespace Tests\Feature;

use Tests\TestCase;

final class UsdtWalletRouteSecurityTest extends TestCase
{
    public function test_guest_cannot_list_wallets(): void
    {
        $this->getJson('/member/usdt-wallets')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_issue_wallet_challenge(): void
    {
        $this->postJson('/member/usdt-wallets/challenge', [
            'network' => 'BEP20',
            'address' => '0x1111111111111111111111111111111111111111',
        ])->assertUnauthorized();
    }

    public function test_guest_cannot_verify_wallet(): void
    {
        $this->postJson('/member/usdt-wallets/verify', [
            'challenge_id' => 1,
            'message' => 'test-message',
            'signature' => 'test-signature',
        ])->assertUnauthorized();
    }
}