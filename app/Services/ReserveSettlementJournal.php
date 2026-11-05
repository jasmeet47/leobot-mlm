<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Counter-entry for a wallet payout from an already funded income reserve. */
final class ReserveSettlementJournal
{
    public static function debit(string $wallet, string $amount, string $before, string $key, string $referenceType, int $referenceId): void
    {
        if (DB::transactionLevel() < 1 || ! in_array($wallet, ['self_reserve', 'generation_pool', 'magic_pool'], true)
            || bccomp($amount, '0', 8) <= 0 || bccomp($before, $amount, 8) < 0) {
            throw new RuntimeException('Invalid atomic reserve settlement.');
        }
        DB::table('financial_ledger')->insert([
            'user_id' => null, 'entry_type' => 'income_reserve_settlement', 'wallet_type' => $wallet,
            'amount' => '-'.$amount, 'balance_before' => $before, 'balance_after' => bcsub($before, $amount, 8),
            'transaction_key' => $key, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
            'status' => 'completed', 'description' => 'Funded reserve released to member wallet',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
