<?php
namespace Tests\Unit;

use App\Services\UsdtPaymentEvidence;
use App\Services\DisabledUsdtChainVerifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UsdtPaymentEvidenceTest extends TestCase
{
    public function test_accepts_formatted_addresses(): void
    {
        UsdtPaymentEvidence::assertAddress('TRC20', 'T'.str_repeat('A',33));
        UsdtPaymentEvidence::assertAddress('BEP20', '0x'.str_repeat('a',40));
        $this->assertTrue(true);
    }
    public function test_rejects_wrong_network_address(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UsdtPaymentEvidence::assertAddress('TRC20', '0x'.str_repeat('a',40));
    }
    public function test_rejects_hash_without_bep20_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UsdtPaymentEvidence::normalizeHash('BEP20', str_repeat('a',64));
    }
    public function test_hash_normalizes_case(): void
    {
        $this->assertSame('0x'.str_repeat('a',64), UsdtPaymentEvidence::normalizeHash('BEP20', '0x'.str_repeat('A',64)));
    }
    public function test_unconfigured_chain_verification_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        (new DisabledUsdtChainVerifier)->verified('BEP20','0x'.str_repeat('a',64),'0x'.str_repeat('b',40),'10');
    }
}
