<?php
namespace App\Services;

use App\Contracts\UsdtWalletOwnershipVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Challenge-only foundation. No wallet is marked verified by this service. */
final class UsdtWalletChallengeService
{
    public function __construct(private UsdtWalletOwnershipVerifier $verifier) {}

    public static function message(int $userId, string $network, string $address, string $nonce, string $expiresAt): string
    {
        if ($userId < 1 || !in_array($network, ['TRC20', 'BEP20'], true)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $nonce)
            || !preg_match('/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ\z/D', $expiresAt)) {
            throw new RuntimeException('Invalid challenge input.');
        }
        UsdtPaymentEvidence::assertAddress($network, $address);
        return "LeoBot USDT Wallet Ownership\n"
            . "Domain: leobot-mlm\nMember: {$userId}\nNetwork: {$network}\n"
            . "Address: {$address}\nNonce: {$nonce}\nExpires: {$expiresAt}\n"
            . "Purpose: verify wallet ownership only; no transfer authorization.";
    }

    /** Generates a fresh challenge; returns nonce and exact message to sign. */
    public function issue(int $userId, string $network, string $address): array
    {
        if ($userId < 1) throw new RuntimeException('Invalid member.');
        $nonce = bin2hex(random_bytes(32));
        $expires = now('UTC')->addMinutes(5)->startOfSecond();
        $iso = $expires->format('Y-m-d\TH:i:s\Z');
        $message = self::message($userId, $network, $address, $nonce, $iso);
        $id = DB::table('usdt_wallet_challenges')->insertGetId([
            'user_id' => $userId, 'network' => $network, 'address' => $address,
            'nonce_hash' => hash('sha256', $nonce), 'message_hash' => hash('sha256', $message),
            'expires_at' => $expires->format('Y-m-d H:i:sP'), 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        return ['challenge_id' => $id, 'message' => $message, 'expires_at' => $iso];
    }

    /** Returns true once for a valid signature. Does NOT enroll a wallet. */
    public function consume(int $userId, int $challengeId, string $message, string $signature): bool
    {
        if ($userId < 1 || $challengeId < 1 || strlen($signature) > 2048 || $signature === '') return false;
        return DB::transaction(function () use ($userId, $challengeId, $message, $signature) {
            $c = DB::table('usdt_wallet_challenges')->where('id', $challengeId)
                ->where('user_id', $userId)->lockForUpdate()->first();
            if (!$c || $c->consumed_at !== null || now('UTC')->greaterThanOrEqualTo(\Carbon\CarbonImmutable::parse($c->expires_at))
                || !hash_equals((string)$c->message_hash, hash('sha256', $message))) return false;
            // Disabled verifier returns false. No challenge is consumed without cryptographic proof.
            if ($this->verifier->verify((string)$c->network, (string)$c->address, $message, $signature) !== true) return false;
            return DB::table('usdt_wallet_challenges')->where('id', $challengeId)
                ->whereNull('consumed_at')->update(['consumed_at' => now('UTC'), 'updated_at' => now('UTC')]) === 1;
        }, 3);
    }
}
