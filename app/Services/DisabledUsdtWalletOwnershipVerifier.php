<?php
namespace App\Services;

use App\Contracts\UsdtWalletOwnershipVerifier;

/** Deliberately prevents wallet enrollment until audited chain-specific verifier is supplied. */
final class DisabledUsdtWalletOwnershipVerifier implements UsdtWalletOwnershipVerifier
{
    public function verify(string $network, string $address, string $message, string $signature): bool
    {
        return false;
    }
}
