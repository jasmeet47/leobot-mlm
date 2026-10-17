<?php
namespace App\Contracts;

/** Independent chain-specific signature verification. Fail closed if no provider is configured. */
interface UsdtWalletOwnershipVerifier
{
    public function verify(string $network, string $address, string $message, string $signature): bool;
}
