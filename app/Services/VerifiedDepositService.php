<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Manual evidence submission; independent on-chain verification required before credit. */
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
        if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) ||
            bccomp($amount, '10', 8) < 0 ||
            bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new InvalidArgumentException('Deposit minimum is 10 USDT; maximum 8 decimals.');
        }
    }

    public static function submit(int $memberId, string $amount, string $network, string $txHash, string $requestKey): int
    {
        self::gate();
        self::amount($amount);
        if ($memberId < 1 || !in_array($network, ['TRC20', 'BEP20'], true) ||
            !preg_match('/\A[A-Za-z0-9]{20,150}\z/D', $txHash) ||
            !preg_match('/\A[A-Za-z0-9_-]{8,100}\z/D', $requestKey)) {
            throw new InvalidArgumentException('Invalid deposit evidence or request key.');
        }
        return DB::transaction(function () use ($memberId, $amount, $network, $txHash, $requestKey): int {
            $user = DB::table('users')->where('id', $memberId)->lockForUpdate()->first();
            if (!$user || $user->status !== 'active' || self::blocked($user)) {
                throw new RuntimeException('Active unblocked member required.');
            }
            $existing = DB::table('member_deposit_requests')->where('request_key', $requestKey)->first();
            if ($existing) {
                if ((int)$existing->user_id !== $memberId ||
                    bccomp((string)$existing->amount, $amount, 8) !== 0 ||
                    $existing->network !== $network || $existing->tx_hash !== $txHash) {
                    throw new RuntimeException('Deposit request key reused with different details.');
                }
                return (int)$existing->id;
            }
            // Defense in depth; the database UNIQUE(network, tx_hash) index is the
            // authoritative concurrency guard against duplicate transaction credit.
            if (DB::table('member_deposit_requests')->where('network', $network)
                ->where('tx_hash', $txHash)->exists()) {
                throw new RuntimeException('Blockchain transaction already submitted.');
            }
            return (int)DB::table('member_deposit_requests')->insertGetId([
                'user_id'=>$memberId, 'request_key'=>$requestKey, 'amount'=>$amount,
                'network'=>$network, 'tx_hash'=>$txHash, 'status'=>'pending',
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }, 3);
    }

    /** Admin must independently verify payment destination, chain, confirmations and amount before calling. */
    public static function approve(int $adminId, int $depositId, string $verificationReference): void
    {
        self::gate();
        if ($adminId < 1 || $depositId < 1 || !preg_match('/\A[A-Za-z0-9._:-]{8,150}\z/D', $verificationReference)) {
            throw new InvalidArgumentException('Invalid approval reference.');
        }
        DB::transaction(function () use ($adminId, $depositId, $verificationReference): void {
            $admin = DB::table('users')->where('id', $adminId)->lockForUpdate()->first();
            if (!$admin || !in_array(strtolower((string)($admin->role ?? '')), ['admin','super_admin'], true) || self::blocked($admin)) {
                throw new RuntimeException('Administrator authorization required.');
            }
            $deposit = DB::table('member_deposit_requests')->where('id', $depositId)->lockForUpdate()->first();
            if (!$deposit || $deposit->status !== 'pending') {
                throw new RuntimeException('Deposit is not pending.');
            }
            if ((int)$deposit->user_id === $adminId) {
                throw new RuntimeException('Self-approval denied.');
            }
            $member = DB::table('users')->where('id', $deposit->user_id)->lockForUpdate()->first();
            if (!$member || $member->status !== 'active' || self::blocked($member)) {
                throw new RuntimeException('Member is inactive or blocked.');
            }
            // Do not approve a transaction hash that appears on another request.
            if (DB::table('member_deposit_requests')->where('network', $deposit->network)
                ->where('tx_hash', $deposit->tx_hash)->where('id', '<>', $depositId)->exists()) {
                throw new RuntimeException('Duplicate blockchain transaction evidence.');
            }
            // Fail closed: admin reference is NOT payment proof. Independent RPC confirmation
            // must match the locked company recipient, network, amount and tx hash.
            if (config('leobot_deposit_chain.enforce_on_chain', false) !== true) {
                throw new RuntimeException('Independent on-chain deposit verification is disabled.');
            }
            $recipient = (string) config('leobot_deposit_chain.recipients.'.$deposit->network, '');
            if ($recipient === '') {
                throw new RuntimeException('Company receiving wallet is not configured for this network.');
            }
            $chain = app(\App\Contracts\UsdtChainVerifier::class);
            (new UsdtPaymentVerificationPolicy($chain))->assertVerified(
                (string)$deposit->network, (string)$deposit->tx_hash,
                $recipient, $recipient, (string)$deposit->amount
            );
            WalletService::credit((int)$deposit->user_id, 'activation_balance', (string)$deposit->amount,
                'verified_deposit', 'deposit:credit:'.$deposit->id,
                ['reference_type'=>'member_deposit_request', 'reference_id'=>$deposit->id,
                 'description'=>'Admin-verified external USDT deposit']);
            DB::table('member_deposit_requests')->where('id', $depositId)->update([
                'status'=>'approved', 'reviewed_by'=>$adminId, 'reviewed_at'=>now(),
                'verification_reference'=>$verificationReference, 'updated_at'=>now(),
            ]);
        }, 3);
    }

    public static function reject(int $adminId, int $depositId): void
    {
        self::gate();
        DB::transaction(function () use ($adminId, $depositId): void {
            $admin = DB::table('users')->where('id',$adminId)->lockForUpdate()->first();
            if (!$admin || !in_array(strtolower((string)($admin->role ?? '')), ['admin','super_admin'], true) || self::blocked($admin)) {
                throw new RuntimeException('Administrator authorization required.');
            }
            $deposit = DB::table('member_deposit_requests')->where('id',$depositId)->lockForUpdate()->first();
            if (!$deposit || $deposit->status !== 'pending') throw new RuntimeException('Deposit is not pending.');
            DB::table('member_deposit_requests')->where('id',$depositId)->update([
                'status'=>'rejected','reviewed_by'=>$adminId,'reviewed_at'=>now(),'updated_at'=>now(),
            ]);
        }, 3);
    }

    private static function blocked(object $user): bool
    {
        return in_array(strtolower((string)($user->is_blocked ?? false)), ['1','t','true'], true);
    }
}
