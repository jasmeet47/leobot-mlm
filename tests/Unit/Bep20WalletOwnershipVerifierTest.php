<?php

namespace Tests\Unit;

use App\Services\Bep20WalletOwnershipVerifier;
use Elliptic\EC;
use kornrunner\Keccak;
use PHPUnit\Framework\TestCase;

final class Bep20WalletOwnershipVerifierTest extends TestCase
{
    public function test_signature_security(): void
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate(str_repeat('0', 63) . '1', 'hex');
        $message = 'LeoBot ownership test';

        $hash = Keccak::hash(
            "\x19Ethereum Signed Message:\n" . strlen($message) . $message,
            256
        );

        $signed = $ec->sign($hash, $key, ['canonical' => true]);

        $signature = '0x'
            . str_pad($signed->r->toString(16), 64, '0', STR_PAD_LEFT)
            . str_pad($signed->s->toString(16), 64, '0', STR_PAD_LEFT)
            . str_pad(dechex($signed->recoveryParam + 27), 2, '0', STR_PAD_LEFT);

        $address = '0x7e5f4552091a69125d5dfcb7b8c2659029395bdf';
        $verifier = new Bep20WalletOwnershipVerifier();

        self::assertTrue($verifier->verify('BEP20', $address, $message, $signature));
        self::assertFalse($verifier->verify('BEP20', '0x' . str_repeat('1', 40), $message, $signature));
        self::assertFalse($verifier->verify('BEP20', $address, $message . ' changed', $signature));
        self::assertFalse($verifier->verify('BEP20', $address, $message, substr($signature, 0, -2) . 'ff'));
        self::assertFalse($verifier->verify('BEP20', $address, $message, '0x1234'));
        self::assertFalse($verifier->verify('TRC20', $address, $message, $signature));
        $hex = substr($signature, 2);
        $r = substr($hex, 0, 64);
        $s = substr($hex, 64, 64);
        $v = substr($hex, 128, 2);
        $n = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
        $nHex = str_pad(gmp_strval($n, 16), 64, '0', STR_PAD_LEFT);
        $highS = str_pad(gmp_strval(gmp_sub($n, gmp_init($s, 16)), 16), 64, '0', STR_PAD_LEFT);

        self::assertFalse($verifier->verify('BEP20', $address, $message, '0x' . str_repeat('0', 64) . $s . $v));
        self::assertFalse($verifier->verify('BEP20', $address, $message, '0x' . $nHex . $s . $v));
        self::assertFalse($verifier->verify('BEP20', $address, $message, '0x' . $r . str_repeat('0', 64) . $v));
        self::assertFalse($verifier->verify('BEP20', $address, $message, '0x' . $r . $nHex . $v));
        self::assertFalse($verifier->verify('BEP20', $address, $message, '0x' . $r . $highS . $v));
        self::assertFalse($verifier->verify('BEP20', $address, '', $signature));
    }
}