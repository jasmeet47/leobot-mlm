<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UsdtWalletEnrollmentService
{
    public function __construct(
        private UsdtWalletChallengeService $challenges
    ) {}

    public function enroll(
        int $userId,
        int $challengeId,
        string $message,
        string $signature
    ): int {
        if ($userId < 1 || $challengeId < 1) {
            throw new RuntimeException('Invalid wallet enrollment request.');
        }

        return DB::transaction(function () use (
            $userId,
            $challengeId,
            $message,
            $signature
        ) {
            MemberPaymentSecurity::assertChangesEnabled();
            $member = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            if (! $member || $member->role !== 'member' || $member->is_blocked || $member->deleted_at !== null) {
                throw new RuntimeException('Eligible member required.');
            }
            $challenge = DB::table('usdt_wallet_challenges')
                ->where('id', $challengeId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $challenge || $challenge->consumed_at !== null) {
                throw new RuntimeException('Invalid or consumed wallet challenge.');
            }

            $network = (string) $challenge->network;
            $address = (string) $challenge->address;

            UsdtPaymentEvidence::assertAddress($network, $address);

            if (! $this->challenges->consume(
                $userId,
                $challengeId,
                $message,
                $signature
            )) {
                throw new RuntimeException('Wallet signature verification failed.');
            }

            $existing = DB::table('usdt_member_wallets')
                ->where('user_id', $userId)
                ->where('network', $network)
                ->where('address', $address)
                ->lockForUpdate()
                ->first();

            $proofDigest = hash('sha256', $message."\n".$signature);

            if ($existing) {
                DB::table('usdt_member_wallets')
                    ->where('id', $existing->id)
                    ->update([
                        'verified_at' => now('UTC'),
                        'revoked_at' => null,
                        'verification_method' => 'signed_challenge',
                        'proof_digest' => $proofDigest,
                        'updated_at' => now('UTC'),
                    ]);

                MemberPaymentSecurity::select($userId, (int) $existing->id, $network);

                return (int) $existing->id;
            }

            $walletId = (int) DB::table('usdt_member_wallets')
                ->insertGetId([
                    'user_id' => $userId,
                    'network' => $network,
                    'address' => $address,
                    'verified_at' => now('UTC'),
                    'revoked_at' => null,
                    'verification_method' => 'signed_challenge',
                    'proof_digest' => $proofDigest,
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            MemberPaymentSecurity::select($userId, $walletId, $network);

            return $walletId;
        }, 3);
    }
}
