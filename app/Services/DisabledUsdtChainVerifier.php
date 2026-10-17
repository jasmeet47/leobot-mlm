<?php
namespace App\Services;

use App\Contracts\UsdtChainVerifier;
use RuntimeException;

/** Fail-closed until a trusted, tested on-chain verifier is implemented and bound. */
final class DisabledUsdtChainVerifier implements UsdtChainVerifier
{
    public function verified(string $network, string $txHash, string $recipient, string $amount): bool
    {
        throw new RuntimeException('On-chain verification is not configured; payout confirmation is disabled.');
    }
}
