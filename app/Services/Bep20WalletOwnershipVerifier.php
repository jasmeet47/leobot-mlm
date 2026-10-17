<?php

namespace App\Services;

use App\Contracts\UsdtWalletOwnershipVerifier;
use Elliptic\EC;
use kornrunner\Keccak;
use Throwable;

final class Bep20WalletOwnershipVerifier implements UsdtWalletOwnershipVerifier
{
    public function verify(
        string $network,
        string $address,
        string $message,
        string $signature
    ): bool {
        if ($network !== 'BEP20'
            || !preg_match('/\A0x[a-fA-F0-9]{40}\z/D', $address)
            || !preg_match('/\A(?:0x)?[a-fA-F0-9]{130}\z/D', $signature)
            || $message === ''
            || strlen($message) > 4096) {
            return false;
        }

        try {
            $hex = strtolower(
                str_starts_with($signature, '0x')
                    ? substr($signature, 2)
                    : $signature
            );

            $r = substr($hex, 0, 64);
            $s = substr($hex, 64, 64);
            $v = hexdec(substr($hex, 128, 2));

            if (!in_array($v, [0, 1, 27, 28], true)) {
                return false;
            }

            $n = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
            $rValue = gmp_init($r, 16);
            $sValue = gmp_init($s, 16);

            if (gmp_cmp($rValue, 0) <= 0 ||
                gmp_cmp($rValue, $n) >= 0 ||
                gmp_cmp($sValue, 0) <= 0 ||
                gmp_cmp($sValue, gmp_div_q($n, 2)) > 0) {
                return false;
            }

            $recovery = $v >= 27 ? $v - 27 : $v;

            $ec = new EC('secp256k1');

            $hash = Keccak::hash(
                "\x19Ethereum Signed Message:\n"
                . strlen($message)
                . $message,
                256
            );

            $sig = ['r' => $r, 's' => $s];

            $public = $ec->recoverPubKey(
                $hash,
                $sig,
                $recovery
            );

            if (!$ec->verify($hash, $sig, $public)) {
                return false;
            }

            $publicHex = $public->encode('hex', false);

            if (strlen($publicHex) !== 130
                || substr($publicHex, 0, 2) !== '04') {
                return false;
            }

            $derived = '0x' . substr(
                Keccak::hash(
                    hex2bin(substr($publicHex, 2)),
                    256
                ),
                -40
            );

            return hash_equals(
                strtolower($address),
                strtolower($derived)
            );
        } catch (Throwable) {
            return false;
        }
    }
}