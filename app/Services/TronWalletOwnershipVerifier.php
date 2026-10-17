<?php
namespace App\Services;

use App\Contracts\UsdtWalletOwnershipVerifier;
use Elliptic\EC;
use kornrunner\Keccak;
use Throwable;

final class TronWalletOwnershipVerifier implements UsdtWalletOwnershipVerifier
{
    public function verify(string $network, string $address, string $message, string $signature): bool
    {
        if ($network !== 'TRC20' || $message === '' || strlen($message) > 4096
            || !preg_match('/\A(?:0x)?[a-fA-F0-9]{130}\z/D', $signature)) return false;

        $expected = TronAddressCodec::decode($address);
        if ($expected === null) return false;

        try {
            $hex = strtolower(str_starts_with($signature, '0x') ? substr($signature, 2) : $signature);
            $r = substr($hex, 0, 64);
            $s = substr($hex, 64, 64);
            $v = hexdec(substr($hex, 128, 2));
            if (!in_array($v, [0, 1, 27, 28], true)) return false;

            $n = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
            $rv = gmp_init($r, 16);
            $sv = gmp_init($s, 16);
            if (gmp_cmp($rv, 0) <= 0 || gmp_cmp($rv, $n) >= 0
                || gmp_cmp($sv, 0) <= 0 || gmp_cmp($sv, gmp_div_q($n, 2)) > 0) return false;

            $hash = Keccak::hash("\x19TRON Signed Message:\n" . strlen($message) . $message, 256);
            $ec = new EC('secp256k1');
            $sig = ['r' => $r, 's' => $s];
            $public = $ec->recoverPubKey($hash, $sig, $v >= 27 ? $v - 27 : $v);
            if (!$ec->verify($hash, $sig, $public)) return false;

            $key = $public->encode('hex', false);
            if (strlen($key) !== 130 || substr($key, 0, 2) !== '04') return false;

            $derived = '41' . substr(Keccak::hash(hex2bin(substr($key, 2)), 256), -40);
            return hash_equals($expected, $derived);
        } catch (Throwable) {
            return false;
        }
    }
}