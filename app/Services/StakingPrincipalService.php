<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StakingPrincipalService
{
    public static function request(User $member, int $positionId, string $amount, string $requestKey, int $walletId, ?string $expectedFeePercent = null): int
    {
        if (config('leobot.withdrawals_enabled', false) !== true || config('leobot.production_financial_approval', false) !== true) {
            throw new RuntimeException('Principal withdrawals require financial approval.');
        }
        self::money($amount);
        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{2,99}\z/D', $requestKey)) {
            throw new RuntimeException('Invalid request reference.');
        }

        return DB::transaction(function () use ($member, $positionId, $amount, $requestKey, $walletId, $expectedFeePercent): int {
            $settings = DB::table('withdrawal_settings')->where('id', 1)->lockForUpdate()->first();
            if (! $settings || ! $settings->member_requests_enabled || ! $settings->principal_release_enabled || ! $settings->principal_fee_configured_at) {
                throw new RuntimeException('Principal withdrawal policy must be configured and enabled by an administrator.');
            }
            $user = User::whereKey($member->id)->lockForUpdate()->firstOrFail();
            if ($user->role !== 'member' || ! in_array($user->status, ['active', 'inactive'], true) || $user->is_blocked || $user->deleted_at !== null) {
                throw new AuthorizationException('Unblocked registered member required.');
            }
            $key = strtoupper($requestKey);
            $old = DB::table('withdrawal_requests')->where('request_key', $key)->first();
            if ($old) {
                $link = DB::table('staking_principal_withdrawals')->where('withdrawal_request_id', $old->id)->first();
                if ((int) $old->user_id !== $user->id || ! $link || (int) $link->staking_position_id !== $positionId
                    || bccomp((string) $old->gross_amount, $amount, 8) !== 0 || (int) $old->usdt_member_wallet_id !== $walletId) {
                    throw new RuntimeException('Withdrawal request key conflict.');
                }

                return (int) $old->id;
            }
            WithdrawalMinimumPolicy::assertAmount($amount);
            $position = DB::table('staking_positions')->where('id', $positionId)->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $position || $position->status !== 'active') {
                throw new RuntimeException('Active member-owned stake required.');
            }
            StakingReconciliationService::assertOpeningJournals($position);
            $before = bcsub((string) $position->amount, (string) $position->withdrawn_amount, 8);
            if (bccomp($before, $amount, 8) < 0) {
                throw new RuntimeException('Insufficient unreserved principal.');
            }
            $wallet = UsdtVerifiedWallet::forWithdrawal($user->id, $walletId);
            $percent = (string) $settings->principal_withdrawal_fee_percent;
            if ($expectedFeePercent !== null && (! preg_match('/\A\d+(?:\.\d{1,9})?\z/D', $expectedFeePercent) || bccomp($percent, $expectedFeePercent, 9) !== 0)) {
                throw new RuntimeException('The fee changed. Reload the page and review the current fee.');
            }
            if (bccomp($percent, '0', 9) < 0 || bccomp($percent, '100', 9) >= 0) {
                throw new RuntimeException('Principal fee must be at least 0% and below 100%.');
            }
            $fee = bcdiv(bcmul($amount, $percent, 17), '100', 8);
            $net = bcadd(bcsub($amount, $fee, 8), '0', $wallet->network === 'TRC20' ? 6 : 8);
            if (bccomp($net, '0', 8) <= 0) {
                throw new RuntimeException('Net payment is below network precision.');
            }
            $fee = bcsub($amount, $net, 8);
            $id = DB::table('withdrawal_requests')->insertGetId([
                'user_id' => $user->id, 'request_key' => $key, 'wallet_type' => 'staking_principal',
                'gross_amount' => $amount, 'fee_amount' => $fee, 'net_amount' => $net, 'status' => 'requested',
                'usdt_member_wallet_id' => $walletId, 'payment_network' => $wallet->network,
                'locked_payment_address' => $wallet->address, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('staking_principal_withdrawals')->insert(['withdrawal_request_id' => $id,
                'staking_position_id' => $positionId, 'fee_percent_snapshot' => $percent, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('staking_positions')->where('id', $positionId)->update([
                'withdrawn_amount' => bcadd((string) $position->withdrawn_amount, $amount, 8), 'updated_at' => now(),
            ]);
            self::journal($user->id, $positionId, $id, '-'.$amount, $before, bcsub($before, $amount, 8), 'hold');
            TeamCounterService::synchronize((int) $user->id);
            DB::table('withdrawal_events')->insert(['withdrawal_request_id' => $id, 'actor_user_id' => $user->id,
                'event_type' => 'principal_requested', 'created_at' => now()]);

            return $id;
        }, 3);
    }

    /** Called inside the locked withdrawal rejection transaction. */
    public static function refund(object $withdrawal): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Principal refund requires a transaction.');
        }
        $link = DB::table('staking_principal_withdrawals')->where('withdrawal_request_id', $withdrawal->id)->first();
        $position = $link ? DB::table('staking_positions')->where('id', $link->staking_position_id)->where('user_id', $withdrawal->user_id)->lockForUpdate()->first() : null;
        if (! $position || bccomp((string) $position->withdrawn_amount, (string) $withdrawal->gross_amount, 8) < 0) {
            throw new RuntimeException('Principal liability does not reconcile.');
        }
        StakingReconciliationService::assertOpeningJournals($position);
        $hold = DB::table('financial_ledger')->where('transaction_key', 'staking-principal:hold:'.$withdrawal->id)->first();
        if (! $hold || (int) $hold->user_id !== (int) $withdrawal->user_id || $hold->wallet_type !== 'staking_principal'
            || $hold->status !== 'completed' || bccomp((string) $hold->amount, '-'.$withdrawal->gross_amount, 8) !== 0
            || $hold->reference_type !== 'staking_position' || (int) $hold->reference_id !== (int) $position->id) {
            throw new RuntimeException('Principal hold journal does not reconcile.');
        }
        $before = bcsub((string) $position->amount, (string) $position->withdrawn_amount, 8);
        DB::table('staking_positions')->where('id', $position->id)->update([
            'withdrawn_amount' => bcsub((string) $position->withdrawn_amount, (string) $withdrawal->gross_amount, 8), 'updated_at' => now(),
        ]);
        self::journal((int) $withdrawal->user_id, (int) $position->id, (int) $withdrawal->id, (string) $withdrawal->gross_amount,
            $before, bcadd($before, (string) $withdrawal->gross_amount, 8), 'refund');
        TeamCounterService::synchronize((int) $withdrawal->user_id);
        MagicMatrixEnrollmentService::synchronize((int) $withdrawal->user_id);
    }

    private static function journal(int $userId, int $positionId, int $withdrawalId, string $amount, string $before, string $after, string $action): void
    {
        DB::table('financial_ledger')->insert(['user_id' => $userId, 'entry_type' => 'staking_principal_'.$action,
            'wallet_type' => 'staking_principal', 'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after,
            'transaction_key' => 'staking-principal:'.$action.':'.$withdrawalId, 'reference_type' => 'staking_position',
            'reference_id' => $positionId, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
    }

    private static function money(string $amount): void
    {
        if (! preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0 || bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new RuntimeException('Invalid principal amount.');
        }
    }
}
