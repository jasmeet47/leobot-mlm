<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StakingService
{
    public function create(int $userId, string $amount, string $key, string $walletType = 'available_balance'): int
    {
        if (! preg_match('/^\d+(\.\d{1,8})?$/D', $amount) || bccomp($amount, '0', 8) <= 0 || bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new RuntimeException('Invalid amount');
        }
        if (! preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)) {
            throw new RuntimeException('Invalid request key');
        }

        if (! in_array($walletType, ['activation_balance', 'available_balance'], true)) {
            throw new RuntimeException('Invalid funding wallet');
        }

        return DB::transaction(function () use ($userId, $amount, $key, $walletType) {
            $control = DB::table('staking_controls')->orderBy('id')->lockForUpdate()->first();
            if (! $control || ! $control->enabled) {
                throw new RuntimeException('Staking disabled');
            }
            self::requireApproval();
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            if ($user->deleted_at !== null || $user->is_blocked || ! in_array($user->status, ['active', 'inactive'], true) || $user->role !== 'member') {
                throw new RuntimeException('Member not eligible');
            }
            $existing = DB::table('staking_positions')->where('request_key', $key)->first();
            if ($existing) {
                if ((int) $existing->user_id !== $userId || bccomp((string) $existing->amount, $amount, 8) !== 0 || $existing->funding_wallet !== $walletType) {
                    throw new RuntimeException('Request key conflict');
                }

                MagicMatrixEnrollmentService::synchronize($userId);

                return (int) $existing->id;
            }
            if (! StakingMemberEligibility::canStake($user, $walletType, $amount)) {
                throw new RuntimeException('Member not eligible');
            }
            $id = DB::table('staking_positions')->insertGetId(['user_id' => $userId, 'amount' => $amount, 'request_key' => $key, 'status' => 'active', 'funding_wallet' => $walletType, 'created_at' => now(), 'updated_at' => now()]);
            WalletService::debit($userId, $walletType, $amount, 'staking_principal', 'staking:'.$key, ['reference_type' => 'staking_position', 'reference_id' => $id]);

            DB::table('financial_ledger')->insert(['user_id' => $userId, 'entry_type' => 'staking_principal_reserved',
                'wallet_type' => 'staking_principal', 'amount' => $amount, 'balance_before' => '0', 'balance_after' => $amount,
                'transaction_key' => 'staking:principal:'.$key, 'reference_type' => 'staking_position', 'reference_id' => $id,
                'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);

            if ($user->status === 'inactive') {
                DB::table('users')->where('id', $userId)->update(['status' => 'active', 'updated_at' => now()]);
                DB::table('member_security_audits')->insert(['actor_id' => $userId, 'action' => 'funded_staking_member_activated', 'details' => json_encode(['user_id' => $userId, 'staking_position_id' => $id, 'wallet_amount_changed' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            TeamCounterService::synchronize($userId);
            MagicMatrixEnrollmentService::synchronize($userId);

            return $id;
        }, 3);
    }

    public static function repairMemberReporting(User $admin, int $memberId): void
    {
        DB::transaction(function () use ($admin, $memberId): void {
            $actor = User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            if (! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new AuthorizationException('Active administrator required.');
            }
            $member = User::whereKey($memberId)->lockForUpdate()->firstOrFail();
            if ($member->role !== 'member' || $member->is_blocked || $member->deleted_at || ! in_array($member->status, ['active', 'inactive'], true)) {
                throw new RuntimeException('Current unblocked member required.');
            }
            $stake = DB::table('staking_positions')->where('user_id', $memberId)->where('status', 'active')->whereRaw('amount > withdrawn_amount')->orderByDesc('created_at')->orderByDesc('id')->lockForUpdate()->first();
            if (! $stake) {
                throw new RuntimeException('Funded remaining staking principal required.');
            }
            StakingReconciliationService::assertOpeningJournals($stake);
            if ($member->status === 'inactive') {
                $manualInactive = DB::table('member_security_audits')->where('action', 'admin_member_status')->whereRaw("details->>'user_id' = ?", [(string) $memberId])->whereRaw("details->>'after' = ?", ['inactive'])->where('created_at', '>=', $stake->created_at)->exists();
                if ($manualInactive) {
                    throw new RuntimeException('Administrator deliberately set this account inactive after staking; manual review required.');
                }
                DB::table('users')->where('id', $memberId)->update(['status' => 'active', 'updated_at' => now()]);
                DB::table('member_security_audits')->insert(['actor_id' => $admin->id, 'action' => 'staking_member_status_repaired', 'details' => json_encode(['user_id' => $memberId, 'staking_position_id' => $stake->id, 'before' => 'inactive', 'after' => 'active', 'wallet_amount_changed' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            TeamCounterService::synchronize($memberId);
        }, 3);
    }

    public function toggle(User $admin, bool $enabled): void
    {
        DB::transaction(function () use ($admin, $enabled) {
            $row = DB::table('staking_controls')->orderBy('id')->lockForUpdate()->first();
            if (! $row) {
                throw new RuntimeException('Staking controls unavailable');
            }
            $actor = User::whereKey($admin->id)->lockForUpdate()->first();
            if (! $actor || $actor->deleted_at !== null || $actor->is_blocked || $actor->status !== 'active' || ! in_array($actor->role, ['admin', 'super_admin'], true)) {
                throw new AuthorizationException('Forbidden');
            }
            if ($enabled) {
                self::requireApproval();
            }
            DB::table('staking_controls')->where('id', $row->id)->update(['enabled' => $enabled, 'updated_at' => now()]);
            DB::table('staking_control_audits')->insert(['admin_id' => $admin->id, 'old_enabled' => $row->enabled, 'new_enabled' => $enabled, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    private static function requireApproval(): void
    {
        if (config('leobot.staking_enabled', false) !== true || config('leobot.production_financial_approval', false) !== true) {
            throw new RuntimeException('Staking requires financial approval');
        }
        if (app()->environment('production')) {
            $policy = DB::table('withdrawal_settings')->where('id', 1)->first();
            if (config('leobot.withdrawals_enabled', false) !== true || config('leobot_usdt_rpc.enabled', false) !== true
                || config('leobot_withdrawal.external_payment_verification_enabled', false) !== true
                || ! $policy || ! $policy->principal_release_enabled || ! $policy->member_requests_enabled
                || ! $policy->admin_review_enabled || ! $policy->principal_fee_configured_at) {
                throw new RuntimeException('Production staking requires an operational principal payout policy and chain verification.');
            }
            foreach (['BEP20', 'TRC20'] as $network) {
                UsdtPaymentEvidence::assertAddress($network, DepositDestinationService::address($network));
            }
            foreach (['bsc_rpc_url', 'tron_full_node_url', 'tron_solidity_node_url'] as $key) {
                $url = (string) config('leobot_usdt_rpc.'.$key, '');
                if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                    throw new RuntimeException('Trusted HTTPS chain endpoints must be configured before production staking.');
                }
            }
        }
    }
}
