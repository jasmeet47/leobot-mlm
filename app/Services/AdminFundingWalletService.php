<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Real company capital, manually attested by an administrator; never trading profit or member income. */
final class AdminFundingWalletService
{
    public const WALLET = 'admin_funding_wallet';

    public static function balance(): string
    {
        return bcadd((string) DB::table('financial_ledger')->whereNull('user_id')->where('wallet_type', self::WALLET)->where('status', 'completed')->sum('amount'), '0', 8);
    }

    public static function topUp(User $admin, string $amount, string $reference, bool $fundsConfirmed, ?string $requestKey = null): int
    {
        self::gate();
        self::money($amount);
        $reference = strtoupper(trim($reference));
        $generatedReference = $reference === '';
        if (! $fundsConfirmed) {
            throw new RuntimeException('Confirm actual company USDT funds received and available.');
        }
        if (($requestKey !== null && ! Str::isUuid($requestKey)) || ($generatedReference && $requestKey === null)) {
            throw new RuntimeException('Reload the funding form for a valid submission key.');
        }
        $requestKey = $requestKey !== null ? strtolower($requestKey) : null;
        if ($generatedReference) {
            $reference = 'AUTO-CAPITAL-'.strtoupper($requestKey);
        }
        if (! preg_match('/\A[A-Z0-9][A-Z0-9._:\/-]{2,119}\z/D', $reference)) {
            throw new RuntimeException('Use a valid optional funding reference, or leave it blank.');
        }
        $key = 'adminfund:receipt:'.hash('sha256', $reference);

        return DB::transaction(function () use ($admin, $amount, $reference, $key, $requestKey, $generatedReference): int {
            self::lock($admin);
            if ($requestKey !== null) {
                $priorSubmission = DB::table('member_security_audits')->where('action', 'admin_funding_received')->whereRaw("details->>'request_key' = ?", [$requestKey])->first();
                if ($priorSubmission) {
                    $details = json_decode($priorSubmission->details, true, 512, JSON_THROW_ON_ERROR);
                    if ((int) $priorSubmission->actor_id !== $admin->id || ($details['source_reference'] ?? '') !== $reference || bccomp((string) ($details['amount'] ?? '-1'), $amount, 8) !== 0) {
                        throw new RuntimeException('Funding submission already used with different details. Reload the form for a new funding entry.');
                    }
                }
            }
            $old = DB::table('financial_ledger')->where('transaction_key', $key)->first();
            if ($old) {
                $proof = self::proof($old);
                if ($old->user_id !== null || $old->wallet_type !== self::WALLET || $old->entry_type !== 'admin_capital_received' || (int) $proof->actor_id !== $admin->id || bccomp((string) $old->amount, $amount, 8) !== 0) {
                    throw new RuntimeException('Funding receipt already recorded with different details.');
                }
                self::assertReconciled();

                return (int) $old->id;
            }
            if (DB::table('member_deposit_requests')->whereRaw('upper(tx_hash) = ?', [$reference])->whereIn('status', ['pending', 'approved'])->exists()
                || DB::table('staking_profit_statements')->whereRaw('upper(evidence_reference) = ?', [$reference])->where('status', '!=', 'rejected')->exists()
                || DB::table('profit_sharing_fundings')->whereRaw('upper(profit_reference) = ?', [$reference])->exists()) {
                throw new RuntimeException('This receipt is already assigned to a member deposit or trading profit. Use unencumbered company capital.');
            }
            $before = self::assertReconciled();
            $after = bcadd($before, $amount, 8);
            self::bounded($after);
            $audit = self::audit($admin->id, 'admin_funding_received', ['amount' => bcadd($amount, '0', 8), 'source_reference' => $reference, 'request_key' => $requestKey, 'reference_generated' => $generatedReference, 'company_funds_confirmed' => true, 'on_chain_verified' => false]);
            $id = self::journal($audit, 'admin_capital_received', self::WALLET, $amount, $before, $after, $key);
            self::journal($audit, 'admin_capital_source', 'external_company_capital', '-'.$amount, $amount, '0', $key.':source');
            self::assertReconciled();

            return $id;
        }, 3);
    }

    public static function activate(User $admin, int $memberId, string $amount, string $requestKey): int
    {
        self::gate(true);
        self::money($amount);
        if (bccomp($amount, '1', 8) < 0 || ! Str::isUuid($requestKey)) {
            throw new RuntimeException('Enter an activation amount of at least 1 USDT and a valid request key.');
        }
        $requestKey = strtolower($requestKey);
        $key = 'adminfund:activation:'.$requestKey;
        $stakeKey = 'adminfund_'.$requestKey;

        return DB::transaction(function () use ($admin, $memberId, $amount, $requestKey, $key, $stakeKey): int {
            self::lock($admin);
            DB::table('staking_controls')->orderBy('id')->lockForUpdate()->first();
            $member = User::whereKey($memberId)->lockForUpdate()->first();
            if (! $member || $member->role !== 'member' || $member->is_blocked || $member->deleted_at || ! in_array($member->status, ['active', 'inactive'], true)) {
                throw new RuntimeException('Choose a current unblocked member.');
            }
            $old = DB::table('financial_ledger')->where('transaction_key', $key)->first();
            if ($old) {
                $proof = self::proof($old);
                if ($old->user_id !== null || $old->wallet_type !== self::WALLET || $old->entry_type !== 'admin_capital_spent' || (int) $proof->actor_id !== $admin->id || (int) $old->reference_user_id !== $memberId || bccomp((string) $old->amount, '-'.$amount, 8) !== 0 || $member->status !== 'active') {
                    throw new RuntimeException('Activation key already used; review the existing funded activation.');
                }
                self::assertReconciled();

                return (int) DB::table('staking_positions')->where('request_key', $stakeKey)->value('id');
            }
            $before = self::assertReconciled();
            if (bccomp($before, $amount, 8) < 0) {
                throw new RuntimeException('Insufficient Admin Funding Wallet balance. Add actual company funds first.');
            }
            $memberBalance = (string) $member->available_balance;
            MemberAccountAdministration::activateAccount($admin, $memberId, $member->status);
            $audit = self::audit($admin->id, 'admin_funded_activation', ['user_id' => $memberId, 'amount' => bcadd($amount, '0', 8), 'request_key' => $requestKey, 'stake_request_key' => $stakeKey, 'capital_before' => $before, 'capital_after' => bcsub($before, $amount, 8), 'source' => 'company_funded_usdt']);
            self::journal($audit, 'admin_capital_spent', self::WALLET, '-'.$amount, $before, bcsub($before, $amount, 8), $key, $memberId);
            WalletService::credit($memberId, 'available_balance', $amount, 'admin_funding_principal_bridge', 'adminfund:member:'.$requestKey, ['reference_type' => 'member_security_audit', 'reference_id' => $audit, 'description' => 'Company funded principal; atomically moved into staking']);
            $position = app(StakingService::class)->create($memberId, $amount, $stakeKey, 'available_balance');
            if (bccomp((string) DB::table('users')->where('id', $memberId)->value('available_balance'), $memberBalance, 8) !== 0) {
                throw new RuntimeException('Funded activation changed the member spendable balance unexpectedly.');
            }
            self::assertReconciled();

            return $position;
        }, 3);
    }

    public static function assertReconciled(): string
    {
        $balance = '0.00000000';
        $rows = DB::table('financial_ledger')->where('wallet_type', self::WALLET)->orderBy('id')->lazyById(200);
        foreach ($rows as $row) {
            if ($row->user_id !== null || $row->status !== 'completed' || bccomp((string) $row->balance_before, $balance, 8) !== 0) {
                throw new RuntimeException('Admin funding journal sequence does not reconcile.');
            }
            $balance = bcadd($balance, (string) $row->amount, 8);
            self::bounded($balance);
            if (bccomp((string) $row->balance_after, $balance, 8) !== 0) {
                throw new RuntimeException('Admin funding journal arithmetic does not reconcile.');
            }
            $audit = self::proof($row);
            $proof = json_decode($audit->details, true, 512, JSON_THROW_ON_ERROR);
            if ($row->entry_type === 'admin_capital_received') {
                $source = DB::table('financial_ledger')->where('transaction_key', $row->transaction_key.':source')->first();
                if ($audit->action !== 'admin_funding_received' || ($proof['company_funds_confirmed'] ?? false) !== true || bccomp((string) $row->amount, '0', 8) <= 0
                    || bccomp((string) ($proof['amount'] ?? '-1'), (string) $row->amount, 8) !== 0 || $row->transaction_key !== 'adminfund:receipt:'.hash('sha256', $proof['source_reference'] ?? '')
                    || ! self::entry($source, null, 'external_company_capital', '-'.$row->amount) || $source->entry_type !== 'admin_capital_source' || (int) $source->reference_id !== (int) $audit->id || $source->reference_type !== 'member_security_audit') {
                    throw new RuntimeException('Admin capital receipt lacks matching source evidence.');
                }
            } elseif ($row->entry_type === 'admin_capital_spent') {
                $memberId = (int) ($proof['user_id'] ?? 0);
                $amount = (string) ($proof['amount'] ?? '0');
                $key = (string) ($proof['request_key'] ?? '');
                $position = DB::table('staking_positions')->where('request_key', 'adminfund_'.$key)->first();
                $bridge = DB::table('financial_ledger')->where('transaction_key', 'adminfund:member:'.$key)->first();
                if ($audit->action !== 'admin_funded_activation' || (int) $row->reference_user_id !== $memberId || bccomp($amount, '0', 8) <= 0 || bccomp((string) $row->amount, '-'.$amount, 8) !== 0
                    || $row->transaction_key !== 'adminfund:activation:'.$key || ! $position || (int) $position->user_id !== $memberId || bccomp((string) $position->amount, $amount, 8) !== 0 || $position->funding_wallet !== 'available_balance'
                    || ! self::entry($bridge, $memberId, 'available_balance', $amount) || $bridge->entry_type !== 'admin_funding_principal_bridge' || (int) $bridge->reference_id !== (int) $audit->id || $bridge->reference_type !== 'member_security_audit') {
                    throw new RuntimeException('Admin-funded staking principal does not reconcile.');
                }
                StakingReconciliationService::assertOpeningJournals($position);
            } else {
                throw new RuntimeException('Unknown Admin Funding Wallet journal entry.');
            }
        }

        return $balance;
    }

    public static function assertSourceNotCapital(string $reference): void
    {
        if (DB::table('financial_ledger')->where('transaction_key', 'adminfund:receipt:'.hash('sha256', strtoupper(trim($reference))))->exists()) {
            throw new RuntimeException('This receipt is already recorded as company capital. Reconcile its original funding before using it elsewhere.');
        }
    }

    private static function lock(User $admin): void
    {
        $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
        if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
            throw new AuthorizationException('Active administrator required.');
        }
        if (! DB::table('company_wallet')->where('id', 1)->lockForUpdate()->first()) {
            throw new RuntimeException('Company serialization account missing.');
        }
        DB::table('company_wallet')->where('id', 1)->update(['updated_at' => now()]);
    }

    private static function gate(bool $activation = false): void
    {
        if (config('leobot.production_financial_approval', false) !== true || ($activation && config('leobot.admin_member_activation_enabled', false) !== true)) {
            throw new RuntimeException('Company funding or admin activation approval is OFF.');
        }
    }

    private static function money(string $amount): void
    {
        if (! preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0) {
            throw new RuntimeException('Enter a positive USDT amount with at most 8 decimals.');
        }
        self::bounded($amount);
    }

    private static function bounded(string $amount): void
    {
        if (bccomp($amount, '0', 8) < 0 || bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new RuntimeException('Admin Funding Wallet amount is outside its limits.');
        }
    }

    private static function proof(object $entry): object
    {
        $audit = $entry->reference_type === 'member_security_audit' ? DB::table('member_security_audits')->find($entry->reference_id) : null;
        if (! $audit) {
            throw new RuntimeException('Admin funding audit is missing.');
        }

        return $audit;
    }

    private static function audit(int $adminId, string $action, array $details): int
    {
        return (int) DB::table('member_security_audits')->insertGetId(['actor_id' => $adminId, 'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private static function journal(int $audit, string $type, string $wallet, string $amount, string $before, string $after, string $key, ?int $memberId = null): int
    {
        return (int) DB::table('financial_ledger')->insertGetId(['user_id' => null, 'entry_type' => $type, 'wallet_type' => $wallet, 'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after, 'transaction_key' => $key, 'reference_type' => 'member_security_audit', 'reference_id' => $audit, 'reference_user_id' => $memberId, 'status' => 'completed', 'description' => 'Actual company capital, manually attested; not a trading-profit receipt', 'created_at' => now(), 'updated_at' => now()]);
    }

    private static function entry(?object $row, ?int $userId, string $wallet, string $amount): bool
    {
        return $row && ($userId === null ? $row->user_id === null : (int) $row->user_id === $userId) && $row->status === 'completed' && $row->wallet_type === $wallet
            && bccomp((string) $row->amount, $amount, 8) === 0 && bccomp(bcadd((string) $row->balance_before, $amount, 8), (string) $row->balance_after, 8) === 0;
    }
}
