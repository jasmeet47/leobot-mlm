<?php

namespace App\Services;

use App\Contracts\IncomingUsdtChainVerifier;
use App\Contracts\UsdtChainVerificationDiagnostics;
use App\Contracts\UsdtChainVerifier;
use App\Exceptions\UsdtVerificationFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/** Pure fail-closed verification policy. No wallet writes, payouts, or routes. */
final class UsdtPaymentVerificationPolicy
{
    public function __construct(private UsdtChainVerifier $chain) {}

    public function assertIncomingVerified(string $network, string $txHash, string $lockedRecipient, string $requestedAmount): ?array
    {
        if (! $this->chain instanceof IncomingUsdtChainVerifier) {
            $this->assertVerified($network, $txHash, $lockedRecipient, $lockedRecipient, $requestedAmount);

            return null;
        }
        UsdtPaymentEvidence::assertAddress($network, $lockedRecipient);
        $hash = UsdtPaymentEvidence::normalizeHash($network, $txHash);
        try {
            return $this->chain->incomingEvidence($network, $hash, $lockedRecipient, $requestedAmount);
        } catch (ConnectionException|RequestException $error) {
            throw new UsdtVerificationFailed('The trusted chain verifier is temporarily unavailable. Keep the request pending and retry later.', 0, $error);
        }
    }

    public function assertVerified(
        string $network, string $txHash, string $verifiedRecipient,
        string $lockedRecipient, string $netAmount
    ): string {
        UsdtPaymentEvidence::assertAddress($network, $verifiedRecipient);
        UsdtPaymentEvidence::assertAddress($network, $lockedRecipient);
        if ($network === 'BEP20' ? strcasecmp($verifiedRecipient, $lockedRecipient) !== 0 : $verifiedRecipient !== $lockedRecipient) {
            throw new RuntimeException('Recipient does not match withdrawal-locked address.');
        }
        if (! preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', $netAmount)
            || bccomp($netAmount, '0', 8) <= 0) {
            throw new RuntimeException('Invalid net payment amount.');
        }
        $hash = UsdtPaymentEvidence::normalizeHash($network, $txHash);
        // This call MUST verify chain, canonical token, receipt, recipient, amount, finality.
        // Default DisabledUsdtChainVerifier throws. Never fall back to admin assertion.
        try {
            $verified = $this->chain->verified($network, $hash, $lockedRecipient, $netAmount);
        } catch (ConnectionException|RequestException $error) {
            throw new UsdtVerificationFailed('The trusted chain verifier is temporarily unavailable. Keep the request pending and retry later.', 0, $error);
        }
        if ($verified !== true) {
            $reason = $this->chain instanceof UsdtChainVerificationDiagnostics ? $this->chain->failureReason() : null;
            throw new UsdtVerificationFailed($reason ?? 'Independent on-chain verification failed.');
        }

        return $hash;
    }
}
