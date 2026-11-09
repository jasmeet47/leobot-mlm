<?php

namespace App\Services;

use App\Contracts\UsdtChainVerifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Incoming USDT must match a configured treasury, canonical token, exact amount and chain finality. */
final class VerifiedDepositService
{
    private static function gate(): void
    {
        if (config('leobot.deposits_enabled', false) !== true ||
            (app()->environment('production') && config('leobot.production_financial_approval', false) !== true)) {
            throw new RuntimeException('Deposits are disabled.');
        }
    }

    private static function amount(string $amount): void
    {
        if (! preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) ||
            bccomp($amount, '10', 8) < 0 ||
            bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new InvalidArgumentException('Deposit minimum is 10 USDT; maximum 8 decimals.');
        }
    }

    public static function submit(int $memberId, string $amount, string $network, string $txHash, string $requestKey, ?string $expectedReceivingAddress = null): int
    {
        self::gate();
        self::amount($amount);
        $txHash = strtolower(trim($txHash));
        $pattern = $network === 'BEP20' ? '/\A0x[a-f0-9]{64}\z/D' : '/\A[a-f0-9]{64}\z/D';
        if ($memberId < 1 || ! in_array($network, ['TRC20', 'BEP20'], true) ||
            ! preg_match($pattern, $txHash) ||
            ! preg_match('/\A[A-Za-z0-9_-]{8,100}\z/D', $requestKey)) {
            throw new InvalidArgumentException('Invalid deposit evidence or request key.');
        }

        $receivingAddress = DepositDestinationService::address($network);
        UsdtPaymentEvidence::assertAddress($network, $receivingAddress);

        return DB::transaction(function () use ($memberId, $amount, $network, $txHash, $requestKey, $receivingAddress, $expectedReceivingAddress): int {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['lumiflowon-deposit-destination:'.$network]);
            $user = DB::table('users')->where('id', $memberId)->lockForUpdate()->first();
            if (! $user || ! in_array($user->status, ['active', 'inactive'], true) || $user->role !== 'member' || $user->deleted_at !== null || self::blocked($user)) {
                throw new RuntimeException('This account is not eligible for deposits. Please contact support.');
            }
            $existing = DB::table('member_deposit_requests')->where('request_key', $requestKey)->first();
            if ($existing) {
                if ((int) $existing->user_id !== $memberId ||
                    bccomp((string) $existing->amount, $amount, 8) !== 0 ||
                    $existing->network !== $network || $existing->tx_hash !== $txHash
                    || ($expectedReceivingAddress !== null && $existing->receiving_address !== $expectedReceivingAddress)) {
                    throw new RuntimeException('Deposit request key reused with different details.');
                }

                return (int) $existing->id;
            }

            if (DepositDestinationService::address($network) !== $receivingAddress || ($expectedReceivingAddress !== null && $expectedReceivingAddress !== $receivingAddress)) {
                throw new RuntimeException('The receiving address changed. Refresh the deposit page before sending or submitting funds.');
            }

            return (int) DB::table('member_deposit_requests')->insertGetId([
                'user_id' => $memberId, 'request_key' => $requestKey, 'amount' => $amount,
                'network' => $network, 'tx_hash' => $txHash, 'receiving_address' => $receivingAddress, 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    /** Legacy caller reference is ignored; the immutable chain/hash produces the unique receipt reference. */
    public static function approve(int $adminId, int $depositId, ?string $verificationReference = null): void
    {
        self::gate();
        if ($adminId < 1 || $depositId < 1) {
            throw new InvalidArgumentException('Invalid deposit approval request.');
        }
        $actor = DB::table('users')->where('id', $adminId)->first();
        if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->deleted_at !== null || self::blocked($actor)) {
            throw new RuntimeException('Administrator authorization required.');
        }
        $snapshot = DB::table('member_deposit_requests')->where('id', $depositId)->first();
        if (! $snapshot || $snapshot->status !== 'pending' || empty($snapshot->receiving_address)) {
            throw new RuntimeException('Pending deposit with an immutable treasury address is required.');
        }
        $verificationReference = 'chain:'.$snapshot->network.':'.$snapshot->tx_hash;
        $chainEvidence = (new UsdtPaymentVerificationPolicy(app(UsdtChainVerifier::class)))->assertIncomingVerified(
            $snapshot->network, $snapshot->tx_hash, $snapshot->receiving_address, (string) $snapshot->amount
        );
        DB::transaction(function () use ($adminId, $depositId, $verificationReference, $snapshot, $chainEvidence): void {
            self::gate();
            $admin = DB::table('users')->where('id', $adminId)->lockForUpdate()->first();
            if (! $admin || ! in_array(strtolower((string) ($admin->role ?? '')), ['admin', 'super_admin'], true) || $admin->status !== 'active' || $admin->deleted_at !== null || self::blocked($admin)) {
                throw new RuntimeException('Administrator authorization required.');
            }
            $deposit = DB::table('member_deposit_requests')->where('id', $depositId)->lockForUpdate()->first();
            if (! $deposit || $deposit->status !== 'pending') {
                throw new RuntimeException('Deposit is not pending.');
            }
            if ($deposit->receiving_address !== $snapshot->receiving_address || $deposit->network !== $snapshot->network
                || $deposit->tx_hash !== $snapshot->tx_hash || bccomp((string) $deposit->amount, (string) $snapshot->amount, 8) !== 0) {
                throw new RuntimeException('Deposit details changed during verification.');
            }
            if ((int) $deposit->user_id === $adminId) {
                throw new RuntimeException('Self-approval denied.');
            }
            $member = DB::table('users')->where('id', $deposit->user_id)->lockForUpdate()->first();
            if (! $member || ! in_array($member->status, ['active', 'inactive'], true) || $member->role !== 'member' || $member->deleted_at !== null || self::blocked($member)) {
                throw new RuntimeException('This account is not eligible for deposit credit. Please contact support.');
            }
            WalletService::credit((int) $deposit->user_id, 'activation_balance', (string) $deposit->amount,
                'verified_deposit', 'deposit:credit:'.$deposit->id,
                ['reference_type' => 'member_deposit_request', 'reference_id' => $deposit->id,
                    'description' => 'Admin-verified external USDT deposit']);
            DB::table('member_deposit_requests')->where('id', $depositId)->update([
                'status' => 'approved', 'reviewed_by' => $adminId, 'reviewed_at' => now(),
                'verification_reference' => $verificationReference, 'updated_at' => now(),
            ]);
            if ($chainEvidence !== null) {
                DB::table('member_security_audits')->insert([
                    'actor_id' => $adminId, 'action' => 'verified_deposit_chain_evidence',
                    'details' => json_encode(['deposit_id' => $depositId, 'user_id' => (int) $deposit->user_id, 'evidence' => $chainEvidence], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
        }, 3);
    }

    public static function reject(int $adminId, int $depositId): void
    {
        self::gate();
        DB::transaction(function () use ($adminId, $depositId): void {
            $admin = DB::table('users')->where('id', $adminId)->lockForUpdate()->first();
            if (! $admin || ! in_array(strtolower((string) ($admin->role ?? '')), ['admin', 'super_admin'], true) || $admin->status !== 'active' || $admin->deleted_at !== null || self::blocked($admin)) {
                throw new RuntimeException('Administrator authorization required.');
            }
            $deposit = DB::table('member_deposit_requests')->where('id', $depositId)->lockForUpdate()->first();
            if (! $deposit || $deposit->status !== 'pending') {
                throw new RuntimeException('Deposit is not pending.');
            }
            DB::table('member_deposit_requests')->where('id', $depositId)->update([
                'status' => 'rejected', 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    private static function blocked(object $user): bool
    {
        return in_array(strtolower((string) ($user->is_blocked ?? false)), ['1', 't', 'true'], true);
    }
}
