<?php

namespace Tests\Unit;

use App\Contracts\UsdtChainVerifier;
use App\Services\DisabledUsdtChainVerifier;
use App\Services\UsdtPaymentVerificationPolicy;
use App\Services\WithdrawalPaymentConfirmationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WithdrawalPaymentPhase4Test extends TestCase
{
    private function sample(string $status = 'approved_awaiting_payment'): object
    {
        return (object) [
            'status' => $status, 'payment_network' => 'BEP20',
            'locked_payment_address' => '0x1111111111111111111111111111111111111111',
            'usdt_member_wallet_id' => 7, 'net_amount' => '10.00000000',
        ];
    }

    public function test_rejected_withdrawal_cannot_be_confirmed(): void
    {
        $this->expectException(RuntimeException::class);
        WithdrawalPaymentConfirmationService::assertPayable($this->sample('rejected'));
    }

    public function test_unlocked_recipient_is_rejected(): void
    {
        $w = $this->sample(); $w->locked_payment_address = null;
        $this->expectException(RuntimeException::class);
        WithdrawalPaymentConfirmationService::assertPayable($w);
    }

    public function test_approved_withdrawal_with_locked_recipient_is_eligible_for_verification(): void
    {
        WithdrawalPaymentConfirmationService::assertPayable($this->sample());
        $this->assertTrue(true);
    }

    public function test_wrong_recipient_fails_before_chain_call(): void
    {
        $chain = $this->createMock(UsdtChainVerifier::class);
        $chain->expects($this->never())->method('verified');
        $this->expectException(RuntimeException::class);
        (new UsdtPaymentVerificationPolicy($chain))->assertVerified(
            'BEP20', '0x' . str_repeat('a', 64),
            '0x2222222222222222222222222222222222222222',
            '0x1111111111111111111111111111111111111111', '10.00000000'
        );
    }

    public function test_invalid_amount_fails_before_chain_call(): void
    {
        $chain = $this->createMock(UsdtChainVerifier::class);
        $chain->expects($this->never())->method('verified');
        $this->expectException(RuntimeException::class);
        (new UsdtPaymentVerificationPolicy($chain))->assertVerified(
            'BEP20', '0x' . str_repeat('a', 64),
            '0x1111111111111111111111111111111111111111',
            '0x1111111111111111111111111111111111111111', '0.00000000'
        );
    }

    public function test_disabled_verifier_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        (new UsdtPaymentVerificationPolicy(new DisabledUsdtChainVerifier()))->assertVerified(
            'BEP20', '0x' . str_repeat('a', 64),
            '0x1111111111111111111111111111111111111111',
            '0x1111111111111111111111111111111111111111', '10.00000000'
        );
    }
}
