<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Variable, manually evidenced realized trading profit; never a promised return. */
final class StakingProfitService
{
    public static function submit(User $admin, string $amount, string $reference, array $allocations): int
    {
        self::gate();
        self::money($amount);
        $reference = strtoupper(trim($reference));
        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{7,99}\z/D', $reference) || ! $allocations || count($allocations) > 1000) {
            throw new RuntimeException('Trading statement evidence and bounded allocations are required.');
        }
        $sum = '0';
        foreach ($allocations as $userId => $share) {
            if (! is_int($userId) || $userId < 1 || ! is_string($share)) {
                throw new RuntimeException('Invalid member allocation.');
            }
            self::money($share);
            $sum = bcadd($sum, $share, 8);
        }
        if (bccomp($sum, $amount, 8) !== 0) {
            throw new RuntimeException('Member allocations must equal realized trading profit.');
        }
        ksort($allocations, SORT_NUMERIC);

        return DB::transaction(function () use ($admin, $amount, $reference, $allocations): int {
            self::admin($admin->id);
            $old = DB::table('staking_profit_statements')->where('evidence_reference', $reference)->first();
            if ($old) {
                $rows = DB::table('staking_profit_allocations')->where('statement_id', $old->id)->orderBy('user_id')->pluck('amount', 'user_id')->all();
                if ((int) $old->submitted_by !== $admin->id || bccomp((string) $old->amount, $amount, 8) !== 0 || array_keys($rows) !== array_keys($allocations)) {
                    throw new RuntimeException('Trading statement reference conflict.');
                }
                foreach ($allocations as $userId => $share) {
                    if (bccomp((string) $rows[$userId], $share, 8) !== 0) {
                        throw new RuntimeException('Trading statement allocation conflict.');
                    }
                }

                return (int) $old->id;
            }
            $id = DB::table('staking_profit_statements')->insertGetId(['amount' => $amount,
                'distribution_version' => 'unified_67_30_3', 'evidence_reference' => $reference, 'submitted_by' => $admin->id, 'status' => 'pending_verification', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($allocations as $userId => $share) {
                if (! DB::table('staking_positions')->where('user_id', $userId)->exists()) {
                    throw new RuntimeException('Profit recipient has no staking history.');
                }
                DB::table('staking_profit_allocations')->insert(['statement_id' => $id, 'user_id' => $userId,
                    'amount' => $share, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        }, 3);
    }

    /** A different administrator must verify the external statement and received funds. */
    public static function approve(User $reviewer, int $statementId): void
    {
        self::gate();
        DB::transaction(function () use ($reviewer, $statementId): void {
            self::admin($reviewer->id);
            $statement = DB::table('staking_profit_statements')->where('id', $statementId)->lockForUpdate()->first();
            if (! $statement || (int) $statement->submitted_by === $reviewer->id) {
                throw new AuthorizationException('Independent trading-profit reviewer required.');
            }
            if (in_array($statement->status, ['credited', 'reserved'], true)) {
                return;
            }
            if ($statement->status !== 'pending_verification') {
                throw new RuntimeException('Trading statement is not pending.');
            }
            $rows = DB::table('staking_profit_allocations')->where('statement_id', $statementId)->orderBy('user_id')->get();
            $sum = '0';
            foreach ($rows as $row) {
                self::money((string) $row->amount);
                $sum = bcadd($sum, (string) $row->amount, 8);
            }
            if (bccomp($sum, (string) $statement->amount, 8) !== 0) {
                throw new RuntimeException('Trading profit does not reconcile.');
            }
            if ($statement->distribution_version !== 'unified_67_30_3') {
                throw new RuntimeException('Legacy pending profit requires independent reconciliation before distribution.');
            }
            $company = DB::table('company_wallet')->where('id', 1)->lockForUpdate()->first();
            if (! $company) {
                throw new RuntimeException('Company reserve account missing.');
            }
            foreach ([['profit_sharing_fundings', 'profit_reference'], ['generation_profit_fundings', 'profit_reference'], ['magic_pool_fundings', 'source_reference']] as [$table, $column]) {
                if (DB::table($table)->where($column, $statement->evidence_reference)->exists()) {
                    throw new RuntimeException('Trading profit evidence already funded; reconcile the existing source.');
                }
            }
            foreach ([['profit_sharing_settings', 'self_profit_percent', '67'], ['generation_pool_settings', 'trading_profit_pool_percent', '30'], ['magic_income_settings', 'trading_profit_pool_percent', '3']] as [$table, $column, $rate]) {
                $settings = DB::table($table)->where('id', 1)->lockForUpdate()->first();
                if (! $settings || bccomp((string) $settings->{$column}, $rate, 9) !== 0) {
                    throw new RuntimeException('Verified trading profit requires the approved 67/30/3 split.');
                }
            }
            $memberProfits = [];
            foreach ($rows as $row) {
                $member = User::whereKey($row->user_id)->first();
                if (! $member || $member->role !== 'member' || $member->status !== 'active' || $member->is_blocked || $member->deleted_at !== null) {
                    throw new RuntimeException('Profit recipient is unavailable.');
                }
                $memberProfits[(int) $row->user_id] = (string) $row->amount;
            }
            $companyAfter = bcadd((string) $company->balance, $sum, 8);
            if (bccomp($companyAfter, '999999999999.99999999', 8) > 0) {
                throw new RuntimeException('Company reserve balance limit exceeded.');
            }
            DB::table('company_wallet')->where('id', 1)->update(['balance' => $companyAfter, 'updated_at' => now()]);
            DB::table('financial_ledger')->insert([
                'entry_type' => 'verified_trading_profit_receipt', 'wallet_type' => 'company_wallet',
                'amount' => $sum, 'balance_before' => $company->balance, 'balance_after' => $companyAfter,
                'transaction_key' => 'staking-profit:receipt:'.$statementId, 'reference_type' => 'staking_profit_statement',
                'reference_id' => $statementId, 'status' => 'completed', 'description' => $statement->evidence_reference,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $fundingId = UnifiedProfitSharingService::fund($reviewer, $sum, $statement->evidence_reference, $memberProfits);
            foreach (array_keys($memberProfits) as $userId) {
                $member = User::whereKey($userId)->lockForUpdate()->first();
                if (! $member || $member->role !== 'member' || $member->status !== 'active' || $member->is_blocked || $member->deleted_at !== null) {
                    throw new RuntimeException('Profit recipient is unavailable.');
                }
            }
            DB::table('financial_ledger')->insert(['entry_type' => 'realized_trading_profit', 'wallet_type' => 'external_trading_profit',
                'amount' => '-'.$sum, 'balance_before' => $sum, 'balance_after' => '0',
                'transaction_key' => 'staking-profit:source:'.$statementId, 'reference_type' => 'staking_profit_statement',
                'reference_id' => $statementId, 'status' => 'completed', 'description' => $statement->evidence_reference,
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('staking_profit_statements')->where('id', $statementId)->update([
                'profit_sharing_funding_id' => $fundingId, 'status' => 'reserved', 'verified_by' => $reviewer->id, 'updated_at' => now(),
            ]);
        }, 3);
    }

    private static function admin(int $id): void
    {
        $admin = User::whereKey($id)->lockForUpdate()->first();
        if (! $admin || $admin->deleted_at !== null || $admin->is_blocked || $admin->status !== 'active' || ! in_array($admin->role, ['admin', 'super_admin'], true)) {
            throw new AuthorizationException('Active administrator required.');
        }
    }

    private static function gate(): void
    {
        if (config('leobot.staking_profit_enabled', false) !== true || config('leobot.production_financial_approval', false) !== true) {
            throw new RuntimeException('Trading-profit distribution is disabled.');
        }
    }

    private static function money(string $amount): void
    {
        if (! preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0 || bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new RuntimeException('Invalid realized profit amount.');
        }
    }
}
