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
                if ($statement->status === 'reversed' && ! self::correctedStatementReconciles($statement)) {
                    $issues[] = 'Profit statement '.$statement->id.': correction audit or reversal journals do not reconcile.';
                }
                $allocations = DB::table('staking_profit_allocations')->where('statement_id', $statement->id)->get();
                $sum = '0';
                foreach ($allocations as $allocation) {
                    $sum = bcadd($sum, (string) $allocation->amount, 8);
                    $entry = DB::table('financial_ledger')->where('transaction_key', 'staking-profit:'.$statement->id.':'.$allocation->user_id)->first();
                    if ((in_array($statement->status, ['credited', 'reserved'], true) && $statement->distribution_version === 'legacy_direct_100' && ! self::entry($entry, (int) $allocation->user_id, 'available_balance', (string) $allocation->amount))
                        || ((in_array($statement->distribution_version, ['unified_67_30_3', MagicSplitPolicy::VERSION], true) || ! in_array($statement->status, ['credited', 'reserved'], true)) && $entry)) {
                        $issues[] = 'Profit statement '.$statement->id.': wallet credits do not match verification status.';
                    }
                }
                if (in_array($statement->distribution_version, ['unified_67_30_3', MagicSplitPolicy::VERSION], true) && in_array($statement->status, ['credited', 'reserved'], true)) {
                    $selfRate = $statement->distribution_version === MagicSplitPolicy::VERSION ? '65' : '67';
                    $magicRate = $statement->distribution_version === MagicSplitPolicy::VERSION ? '5' : '3';
                    $funding = DB::table('profit_sharing_fundings')->where('id', $statement->profit_sharing_funding_id)->first();
                    $receipt = DB::table('financial_ledger')->where('transaction_key', 'staking-profit:receipt:'.$statement->id)->first();
                    if (! $funding || $funding->profit_reference !== $statement->evidence_reference
                        || bccomp((string) $funding->verified_profit_amount, (string) $statement->amount, 8) !== 0
                        || bccomp((string) $funding->self_percent_snapshot, $selfRate, 9) !== 0
                        || bccomp((string) $funding->generation_percent_snapshot, '30', 9) !== 0
                        || bccomp((string) $funding->magic_percent_snapshot, $magicRate, 9) !== 0
                        || ! self::entry($receipt, null, 'company_wallet', (string) $statement->amount)) {
                        $issues[] = 'Profit statement '.$statement->id.': unified reserve funding does not reconcile.';
                    } else {
                        $selfTotal = '0';
                        foreach ($allocations as $allocation) {
                            $selfTotal = bcadd($selfTotal, bcdiv(bcmul((string) $allocation->amount, $selfRate, 17), '100', 8), 8);
                        }
                        $genTotal = bcdiv(bcmul((string) $statement->amount, '30', 17), '100', 8);
                        $magicTotal = bcdiv(bcmul((string) $statement->amount, $magicRate, 17), '100', 8);
                        $fundedTotal = bcadd(bcadd($selfTotal, $genTotal, 8), $magicTotal, 8);
                        foreach (['self_amount' => $selfTotal, 'generation_amount' => $genTotal, 'magic_amount' => $magicTotal,
                            'funded_total' => $fundedTotal, 'company_rounding_remainder' => bcsub((string) $statement->amount, $fundedTotal, 8)] as $field => $expected) {
                            if (bccomp((string) $funding->{$field}, $expected, 8) !== 0) {
                                $issues[] = 'Profit statement '.$statement->id.': funded amount or rounding mismatch.';
                            }
                        }
                        $hash = hash('sha256', $statement->evidence_reference);
                        foreach ([['company', 'company_wallet', '-'.$fundedTotal], ['self', 'self_reserve', $selfTotal],
                            ['generation', 'generation_pool', $genTotal], ['magic', 'magic_pool', $magicTotal]] as [$suffix, $wallet, $expected]) {
                            $journal = DB::table('financial_ledger')->where('transaction_key', 'profitsharing:'.$suffix.':'.$hash)->first();
                            if (bccomp($expected, '0', 8) !== 0 && (! self::entry($journal, null, $wallet, $expected)
                                || $journal->reference_type !== 'profit_sharing_funding' || (int) $journal->reference_id !== (int) $funding->id)) {
                                $issues[] = 'Profit statement '.$statement->id.': reserve funding journal mismatch.';
                            }
                        }
                        if (DB::table('profit_sharing_self_reserves')->where('funding_id', $funding->id)->count() !== $allocations->count()) {
                            $issues[] = 'Profit statement '.$statement->id.': reserve owner count mismatch.';
                        }
                        foreach ($allocations as $allocation) {
                            $reserve = DB::table('profit_sharing_self_reserves')->where('funding_id', $funding->id)->where('user_id', $allocation->user_id)->first();
                            if (! $reserve || bccomp((string) $reserve->member_profit_amount, (string) $allocation->amount, 8) !== 0
                                || bccomp((string) $reserve->self_share_reserved, bcdiv(bcmul((string) $allocation->amount, $selfRate, 17), '100', 8), 8) !== 0) {
                                $issues[] = 'Profit statement '.$statement->id.': member Self reserve mismatch.';
                            }
                        }
                    }
                }
                if (($statement->verification_method ?? 'independent_admin') === 'independent_member_attestation'
                    && in_array($statement->status, ['credited', 'reserved', 'independently_verified'], true)
                    && ! TradingProfitReviewerService::historicalAttestationMatches($statement, $allocations)) {
                    $issues[] = 'Profit statement '.$statement->id.': immutable independent review does not match the evidence.';
                }
                $source = DB::table('financial_ledger')->where('transaction_key', 'staking-profit:source:'.$statement->id)->first();
                $approvalMatches = $statement->verified_by && (($statement->verification_method ?? null) === 'admin_manual_confirmation' ? StakingProfitService::manualApprovalMatches($statement, $allocations) : (int) $statement->verified_by !== (int) $statement->submitted_by);
                if (bccomp($sum, (string) $statement->amount, 8) !== 0 || (in_array($statement->status, ['credited', 'reserved'], true)
                    && (! $approvalMatches
                        || ! self::entry($source, null, 'external_trading_profit', '-'.$statement->amount)))) {
                    $issues[] = 'Profit statement '.$statement->id.': source total or recorded approval does not reconcile.';
                }
            }
        });

        return ['positions_checked' => $positions, 'issue_count' => count($issues), 'issues' => array_slice($issues, 0, 50),
            'accounting_passed' => $issues === [], 'on_chain_reserve_solvency' => 'NOT VERIFIED'];
    }

    private static function correctedStatementReconciles(object $statement): bool
    {
        $audit = DB::table('member_security_audits')->where('action', 'legacy_income_corrected')->whereRaw("details->>'statement_id' = ?", [(string) $statement->id])->first();
        $funding = DB::table('profit_sharing_fundings')->where('id', $statement->profit_sharing_funding_id)->first();
        if (! $audit || ! $funding || $funding->status !== 'reversed') {
            return false;
        }
        $details = json_decode($audit->details, true);
        $replacement = DB::table('staking_profit_statements')->where('id', $details['replacement_statement_id'] ?? 0)->first();
        if (! $replacement || $replacement->status !== 'reserved' || $replacement->distribution_version !== MagicSplitPolicy::VERSION || bccomp((string) $replacement->amount, (string) $statement->amount, 8) !== 0) {
            return false;
        }
        $selfIds = DB::table('profit_sharing_self_reserves')->where('funding_id', $funding->id)->pluck('id');
        $credits = DB::table('financial_ledger')->where('wallet_type', 'available_balance')->where('status', 'completed')->where('amount', '>', 0)->where(function ($q) use ($selfIds, $funding): void {
            $q->where(function ($q) use ($selfIds): void {
                $q->where('reference_type', 'profit_sharing_self_reserve')->whereIn('reference_id', $selfIds)->where('entry_type', 'self_income_settlement');
            })
                ->orWhere(function ($q) use ($funding): void {
                    $q->where('reference_type', 'profit_sharing_funding')->where('reference_id', $funding->id)->where('entry_type', 'magic_income_settlement');
                });
        })->get();
        $self = '0';
        $magic = '0';
        foreach ($credits as $credit) {
            $reverse = DB::table('financial_ledger')->where('transaction_key', 'income:reverse:'.$credit->id)->first();
            if (! self::entry($reverse, (int) $credit->user_id, 'available_balance', '-'.$credit->amount) || $reverse->entry_type !== 'income_correction_reversal' || $reverse->reference_type !== 'financial_ledger' || (int) $reverse->reference_id !== (int) $credit->id) {
                return false;
            }
            if ($credit->entry_type === 'self_income_settlement') {
                $self = bcadd($self, (string) $credit->amount, 8);
            } else {
                $magic = bcadd($magic, (string) $credit->amount, 8);
            }
        }
        if (bccomp(bcadd($self, $magic, 8), (string) ($details['reversed_paid'] ?? '-1'), 8) !== 0 || $credits->count() !== ($details['credit_rows'] ?? -1)) {
            return false;
        }
        foreach ([['self_reserve', $self, 'payout-return'], ['magic_pool', $magic, 'payout-return'], ['self_reserve', '-'.$funding->self_amount, 'unfund'], ['magic_pool', '-'.$funding->magic_amount, 'unfund'], ['generation_pool', '-'.$funding->generation_amount, 'unfund'], ['company_wallet', (string) $funding->funded_total, 'unfund'], ['company_wallet', '-'.$statement->amount, 'receipt-reversal'], ['external_trading_profit', (string) $statement->amount, 'source-reversal']] as [$wallet, $amount, $suffix]) {
            if (bccomp($amount, '0', 8) === 0) {
                continue;
            }
            if (! self::entry(DB::table('financial_ledger')->where('transaction_key', 'income:correction:'.$statement->id.':'.$wallet.':'.$suffix)->first(), null, $wallet, $amount)) {
                return false;
            }
        }

        return true;
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
