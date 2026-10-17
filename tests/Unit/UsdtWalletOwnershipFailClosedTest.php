<?php
namespace Tests\Unit;

use App\Services\DisabledUsdtWalletOwnershipVerifier;
use PHPUnit\Framework\TestCase;

final class UsdtWalletOwnershipFailClosedTest extends TestCase
{
    public function test_disabled_verifier_rejects_trc20_claim(): void
    {
        $this->assertFalse((new DisabledUsdtWalletOwnershipVerifier())->verify('TRC20', 'T'.str_repeat('A', 33), 'challenge', 'fake'));
    }
    public function test_disabled_verifier_rejects_bep20_claim(): void
    {
        $this->assertFalse((new DisabledUsdtWalletOwnershipVerifier())->verify('BEP20', '0x'.str_repeat('a', 40), 'challenge', 'fake'));
    }
}
