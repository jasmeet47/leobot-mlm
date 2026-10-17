<?php
namespace App\Services;

final class TronAddressCodec
{
    public static function decode(string $address): ?string
    {
        if (!preg_match('/\AT[1-9A-HJ-NP-Za-km-z]{33}\z/D', $address)) return null;

        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $number = gmp_init(0);

        foreach (str_split($address) as $char) {
            $digit = strpos($alphabet, $char);
            if ($digit === false) return null;
            $number = gmp_add(gmp_mul($number, 58), $digit);
        }

        $hex = gmp_strval($number, 16);
        if (strlen($hex) % 2) $hex = '0' . $hex;

        $bytes = hex2bin($hex);
        $leading = strlen($address) - strlen(ltrim($address, '1'));
        $bytes = str_repeat("\x00", $leading) . $bytes;

        if (strlen($bytes) !== 25) return null;

        $payload = substr($bytes, 0, 21);
        $checksum = substr($bytes, 21, 4);
        $expected = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        if (!hash_equals($expected, $checksum) || ord($payload[0]) !== 0x41) return null;

        return bin2hex($payload);
    }
}