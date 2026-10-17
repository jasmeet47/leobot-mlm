<?php
namespace App\Services;

use App\Contracts\UsdtChainVerifier;
use RuntimeException;

/** Pure fail-closed verification policy. No wallet writes, payouts, or routes. */
final class UsdtPaymentVerificationPolicy
{
    public function __construct(private UsdtChainVerifier $chain) {}

    public function assertVerified(
        string $network, string $txHash, string $verifiedRecipient,
        string $lockedRecipient, string $netAmount
    ): string {
        UsdtPaymentEvidence::assertAddress($network, $verifiedRecipient);
        UsdtPaymentEvidence::assertAddress($network, $lockedRecipient);
        if ($network === 'BEP20' ? strcasecmp($verifiedRecipient, $lockedRecipient) !== 0 : $verifiedRecipient !== $lockedRecipient) {
            throw new RuntimeException('Recipient does not match withdrawal-locked address.');
        }
        if (!preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', $netAmount)
            || bccomp($netAmount, '0', 8) <= 0) {
            throw new RuntimeException('Invalid net payment amount.');
        }
        $hash = UsdtPaymentEvidence::normalizeHash($network, $txHash);
        // This call MUST verify chain, canonical token, receipt, recipient, amount, finality.
        // Default DisabledUsdtChainVerifier throws. Never fall back to admin assertion.
        if ($this->chain->verified($network, $hash, $lockedRecipient, $netAmount) !== true) {
            throw new RuntimeException('Independent on-chain verification failed.');
        }
        return $hash;
    }
}
