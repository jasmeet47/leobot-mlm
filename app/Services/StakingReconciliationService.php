<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Read-only comparison of staking liabilities, withdrawal holds and profit journals. */
final class StakingReconciliationService
{
    public static function inspect(): array
    {
        $issues = [];
        $positions = 0;
        DB::table('staking_positions')->orderBy('id')->chunkById(200, function ($rows) use (&$issues, &$positions): void {
            foreach ($rows as $position) {
                $positions++;
                $debit = DB::table('financial_ledger')->where('transaction_key', 'staking:'.$position->request_key)->first();
                $reserve = DB::table('financial_ledger')->where('transaction_key', 'staking:principal:'.$position->request_key)->first();
                if (! self::entry($debit, (int) $position->user_id, $position->funding_wallet, '-'.$position->amount)
                    || ! self::entry($reserve, (int) $position->user_id, 'staking_principal', (string) $position->amount)) {
                    $issues[] = 'Stake '.$position->id.': opening wallet/principal journals missing or inconsistent; legacy entries require independent reconciliation.';
                }
                $withdrawals = DB::table('staking_principal_withdrawals as p')->join('withdrawal_requests as w', 'w.id', '=', 'p.withdrawal_request_id')
                    ->where('p.staking_position_id', $position->id)->select('w.*', 'p.fee_percent_snapshot')->get();
                $reserved = '0';
                foreach ($withdrawals as $withdrawal) {
                    if ($withdrawal->status !== 'rejected') {
                        $reserved = bcadd($reserved, (string) $withdrawal->gross_amount, 8);
                    }
                    if ((int) $withdrawal->user_id !== (int) $position->user_id
                        || ! in_array($withdrawal->status, ['requested', 'approved_awaiting_payment', 'payment_processing', 'payment_submitted', 'paid', 'rejected'], true)
                        || bccomp(bcadd((string) $withdrawal->fee_amount, (string) $withdrawal->net_amount, 8), (string) $withdrawal->gross_amount, 8) !== 0
                        || bccomp((string) $withdrawal->net_amount, '0', 8) <= 0) {
                        $issues[] = 'Withdrawal '.$withdrawal->id.': member, status or net/fee invariant failed.';
                    }
                    $hold = DB::table('financial_ledger')->where('transaction_key', 'staking-principal:hold:'.$withdrawal->id)->first();
                    if (! self::entry($hold, (int) $position->user_id, 'staking_principal', '-'.$withdrawal->gross_amount)) {
                        $issues[] = 'Withdrawal '.$withdrawal->id.': principal hold journal missing or inconsistent.';
                    }
                    $refund = DB::table('financial_ledger')->where('transaction_key', 'staking-principal:refund:'.$withdrawal->id)->first();
                    if (($withdrawal->status === 'rejected' && ! self::entry($refund, (int) $position->user_id, 'staking_principal', (string) $withdrawal->gross_amount))
                        || ($withdrawal->status !== 'rejected' && $refund)) {
                        $issues[] = 'Withdrawal '.$withdrawal->id.': refund does not match status.';
                    }
                    if ($withdrawal->status === 'paid') {
                        $evidence = DB::table('withdrawal_payment_evidence')->where('withdrawal_request_id', $withdrawal->id)->first();
                        $submission = DB::table('withdrawal_payment_submissions')->where('withdrawal_request_id', $withdrawal->id)->first();
                        if (! $evidence || ! $submission || $evidence->tx_hash !== $submission->tx_hash || $evidence->network !== $withdrawal->payment_network
                            || $evidence->recipient !== $withdrawal->locked_payment_address || bccomp((string) $evidence->amount, (string) $withdrawal->net_amount, 8) !== 0) {
                            $issues[] = 'Withdrawal '.$withdrawal->id.': paid state lacks matching verified chain evidence.';
                        }
                    }
                }
                if (bccomp($reserved, (string) $position->withdrawn_amount, 8) !== 0 || bccomp($reserved, (string) $position->amount, 8) > 0) {
                    $issues[] = 'Stake '.$position->id.': principal balance does not match unpaid/paid withdrawal liabilities.';
                }
            }
        });
        $orphans = DB::table('withdrawal_requests as w')->leftJoin('staking_principal_withdrawals as p', 'p.withdrawal_request_id', '=', 'w.id')
            ->where('w.wallet_type', 'staking_principal')->whereNull('p.id')->count();
        if ($orphans > 0) {
            $issues[] = 'Principal withdrawal requests without position links: '.$orphans;
        }
        DB::table('staking_profit_statements')->orderBy('id')->chunkById(200, function ($rows) use (&$issues): void {
            foreach ($rows as $statement) {
                $allocations = DB::table('staking_profit_allocations')->where('statement_id', $statement->id)->get();
                $sum = '0';
                foreach ($allocations as $allocation) {
                    $sum = bcadd($sum, (string) $allocation->amount, 8);
                    $entry = DB::table('financial_ledger')->where('transaction_key', 'staking-profit:'.$statement->id.':'.$allocation->user_id)->first();
                    if (($statement->status === 'credited' && ! self::entry($entry, (int) $allocation->user_id, 'available_balance', (string) $allocation->amount))
                        || ($statement->status !== 'credited' && $entry)) {
                        $issues[] = 'Profit statement '.$statement->id.': wallet credits do not match verification status.';
                    }
                }
                $source = DB::table('financial_ledger')->where('transaction_key', 'staking-profit:source:'.$statement->id)->first();
                if (bccomp($sum, (string) $statement->amount, 8) !== 0 || ($statement->status === 'credited'
                    && (! $statement->verified_by || (int) $statement->verified_by === (int) $statement->submitted_by
                        || ! self::entry($source, null, 'external_trading_profit', '-'.$statement->amount)))) {
                    $issues[] = 'Profit statement '.$statement->id.': source total or independent review does not reconcile.';
                }
            }
        });

        return ['positions_checked' => $positions, 'issue_count' => count($issues), 'issues' => array_slice($issues, 0, 50),
            'accounting_passed' => $issues === [], 'on_chain_reserve_solvency' => 'NOT VERIFIED'];
    }

    public static function assertOpeningJournals(object $position): void
    {
        $debit = DB::table('financial_ledger')->where('transaction_key', 'staking:'.$position->request_key)->first();
        $reserve = DB::table('financial_ledger')->where('transaction_key', 'staking:principal:'.$position->request_key)->first();
        if (! self::entry($debit, (int) $position->user_id, $position->funding_wallet, '-'.$position->amount)
            || ! self::entry($reserve, (int) $position->user_id, 'staking_principal', (string) $position->amount)
            || $debit->reference_type !== 'staking_position' || (int) $debit->reference_id !== (int) $position->id
            || $reserve->reference_type !== 'staking_position' || (int) $reserve->reference_id !== (int) $position->id) {
            throw new \RuntimeException('Principal opening journals require independent reconciliation.');
        }
    }

    private static function entry(?object $entry, ?int $userId, string $wallet, string $amount): bool
    {
        return $entry !== null && ($userId === null ? $entry->user_id === null : (int) $entry->user_id === $userId)
            && $entry->wallet_type === $wallet && $entry->status === 'completed'
            && bccomp((string) $entry->amount, $amount, 8) === 0
            && bccomp(bcadd((string) $entry->balance_before, (string) $entry->amount, 8), (string) $entry->balance_after, 8) === 0;
    }
}
