<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use kornrunner\Keccak;

final class DepositDestinationService
{
    public static function current(string $network): array
    {
        if (! in_array($network, ['BEP20', 'TRC20'], true)) {
            throw new InvalidArgumentException('Unsupported USDT network.');
        }
        $row = Schema::hasTable('deposit_destinations') ? DB::table('deposit_destinations')->where('network', $network)->orderByDesc('id')->first() : null;

        return ['id' => $row?->id, 'network' => $network, 'address' => $row?->address ?? (string) config('leobot_deposits.receiving_addresses.'.$network, ''), 'has_qr' => ! empty($row?->qr_base64)];
    }

    public static function address(string $network): string
    {
        return self::current($network)['address'];
    }

    public static function validateAddress(string $network, string $address): void
    {
        UsdtPaymentEvidence::assertAddress($network, $address);
        if ($network === 'BEP20') {
            $hex = substr($address, 2);
            if (preg_match('/^0+$/D', $hex)) {
                throw new InvalidArgumentException('The zero address cannot receive deposits.');
            }
            if ($hex !== strtolower($hex) && $hex !== strtoupper($hex)) {
                $hash = Keccak::hash(strtolower($hex), 256);
                foreach (str_split($hex) as $index => $character) {
                    if (ctype_alpha($character) && (ctype_upper($character) !== (hexdec($hash[$index]) >= 8))) {
                        throw new InvalidArgumentException('BEP20 address checksum is invalid.');
                    }
                }
            }

            return;
        }
        $number = '0';
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        foreach (str_split($address) as $character) {
            $number = bcadd(bcmul($number, '58', 0), (string) strpos($alphabet, $character), 0);
        }
        $hex = '';
        while (bccomp($number, '0', 0) > 0) {
            $hex = str_pad(dechex((int) bcmod($number, '256')), 2, '0', STR_PAD_LEFT).$hex;
            $number = bcdiv($number, '256', 0);
        }
        if (strlen($hex) !== 50 || substr($hex, 0, 2) !== '41'
            || ! hash_equals(substr(hash('sha256', hash('sha256', hex2bin(substr($hex, 0, 42)), true)), 0, 8), substr($hex, 42, 8))) {
            throw new InvalidArgumentException('TRC20 address checksum is invalid.');
        }
    }
}
