<?php
namespace App\Services;

use InvalidArgumentException;

/** Pure validation only. Never interprets a transaction hash as proof of payment. */
final class UsdtPaymentEvidence
{
    public static function assertAddress(string $network, string $address): void
    {
        if (!in_array($network, ['TRC20', 'BEP20'], true)) {
            throw new InvalidArgumentException('Unsupported USDT network.');
        }
        if ($network === 'TRC20') {
            // Format-only check: base58check checksum still needs validation by a trusted provider.
            if (!preg_match('/\AT[1-9A-HJ-NP-Za-km-z]{33}\z/D', $address)) {
                throw new InvalidArgumentException('Invalid TRC20 address format.');
            }
        } elseif (!preg_match('/\A0x[a-fA-F0-9]{40}\z/D', $address)) {
            throw new InvalidArgumentException('Invalid BEP20 address format.');
        }
    }

    public static function normalizeHash(string $network, string $hash): string
    {
        if (!in_array($network, ['TRC20', 'BEP20'], true)) {
            throw new InvalidArgumentException('Unsupported USDT network.');
        }
        if ($network === 'BEP20') {
            if (!preg_match('/\A0x[a-fA-F0-9]{64}\z/D', $hash)) {
                throw new InvalidArgumentException('Invalid BEP20 hash.');
            }
            return strtolower($hash);
        }
        if (!preg_match('/\A[a-fA-F0-9]{64}\z/D', $hash)) {
            throw new InvalidArgumentException('Invalid TRC20 hash.');
        }
        return strtolower($hash);
    }
}
