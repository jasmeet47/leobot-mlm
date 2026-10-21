<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Disabled-by-default member transfer core.
 * No route/UI is registered by this patch.
 */
final class MemberFundTransferService
{
    public static function transfer(int $senderId, int $recipientId, string $amount, string $requestKey): void
    {
        if (config('leobot.fund_transfers_enabled', false) !== true ||
            (app()->environment('production') &&
             config('leobot.production_financial_approval', false) !== true)) {
            throw new RuntimeException('Fund transfers are disabled.');
        }
        if ($senderId < 1 || $recipientId < 1 || $senderId === $recipientId) {
            throw new InvalidArgumentException('Invalid transfer participants.');
        }
        if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) ||
            bccomp($amount, '0', 8) <= 0 ||
            bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new InvalidArgumentException('Invalid transfer amount.');
        }
        if (!preg_match('/\A[A-Za-z0-9_-]{8,100}\z/D', $requestKey)) {
            throw new InvalidArgumentException('Invalid transfer reference.');
        }

        DB::transaction(function () use ($senderId, $recipientId, $amount, $requestKey): void {
            // Deterministic lock order avoids opposite-direction transfer deadlocks.
            $ids = [$senderId, $recipientId];
            sort($ids, SORT_NUMERIC);
            $users = DB::table('users')->whereIn('id', $ids)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($users->count() !== 2) {
                throw new RuntimeException('Transfer participant not found.');
            }
            foreach ($ids as $id) {
                $user = $users->get($id);
                if (($user->status ?? null) !== 'active' ||
                    in_array(strtolower((string)($user->is_blocked ?? false)), ['1','t','true'], true)) {
                    throw new RuntimeException('Both accounts must be active and unblocked.');
                }
            }

            $base = 'member-transfer:' . $requestKey;
            $debitKey = $base . ':debit';
            $creditKey = $base . ':credit';
            $existing = DB::table('financial_ledger')
                ->whereIn('transaction_key', [$debitKey, $creditKey])->get();
            if ($existing->isNotEmpty()) {
                // Fail closed on retries: no duplicate transfer and no silent mismatch.
                throw new RuntimeException('Transfer reference already used.');
            }

            WalletService::debit($senderId, 'available_balance', $amount,
                'member_transfer_out', $debitKey, [
                    'reference_user_id' => $recipientId,
                    'description' => 'Member-to-member transfer debit',
                ]);
            WalletService::credit($recipientId, 'available_balance', $amount,
                'member_transfer_in', $creditKey, [
                    'from_user_id' => $senderId,
                    'description' => 'Member-to-member transfer credit',
                ]);
            // Nested WalletService transactions participate in the outer atomic transaction.
        }, 3);
    }
}
