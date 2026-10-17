<?php
namespace Tests\Unit;

use App\Contracts\UsdtChainVerifier;
use App\Services\DisabledUsdtChainVerifier;
use App\Services\UsdtPaymentVerificationPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UsdtPaymentVerificationPolicyTest extends TestCase
{
    private const ADDRESS = '0x1111111111111111111111111111111111111111';
    private const OTHER = '0x2222222222222222222222222222222222222222';
    private const HASH = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_wrong_recipient_fails_before_chain_call(): void
    {
        $chain = $this->createMock(UsdtChainVerifier::class);
        $chain->expects($this->never())->method('verified');
        $this->expectException(RuntimeException::class);
        (new UsdtPaymentVerificationPolicy($chain))->assertVerified('BEP20', self::HASH, self::OTHER, self::ADDRESS, '10.00000000');
    }
    public function test_invalid_amount_fails_before_chain_call(): void
    {
        $chain = $this->createMock(UsdtChainVerifier::class);
        $chain->expects($this->never())->method('verified');
        $this->expectException(RuntimeException::class);
        (new UsdtPaymentVerificationPolicy($chain))->assertVerified('BEP20', self::HASH, self::ADDRESS, self::ADDRESS, '0');
    }
    public function test_disabled_chain_verifier_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        (new UsdtPaymentVerificationPolicy(new DisabledUsdtChainVerifier()))->assertVerified('BEP20', self::HASH, self::ADDRESS, self::ADDRESS, '10');
    }
    public function test_verified_chain_receipt_returns_normalized_hash(): void
    {
        $chain = $this->createMock(UsdtChainVerifier::class);
        $chain->expects($this->once())->method('verified')->with('BEP20', self::HASH, self::ADDRESS, '10')->willReturn(true);
        $this->assertSame(self::HASH, (new UsdtPaymentVerificationPolicy($chain))->assertVerified('BEP20', '0x' . strtoupper(substr(self::HASH, 2)), self::ADDRESS, self::ADDRESS, '10'));
    }
    public function test_unverified_chain_receipt_is_rejected(): void
    {
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn(false);
        $this->expectException(RuntimeException::class);
        (new UsdtPaymentVerificationPolicy($chain))->assertVerified('BEP20', self::HASH, self::ADDRESS, self::ADDRESS, '10');
    }
}
