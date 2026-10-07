
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WalletService
{
    private const ALLOWED_WALLETS = [
        'available_balance',
        'activation_balance',
    ];

    public static function credit(
        int $userId,
        string $walletType,
        string $amount,
        string $entryType,
        string $transactionKey,
        array $details = []
    ): void {
        self::changeBalance(
            $userId,
            $walletType,
            $amount,
            $entryType,
            $transactionKey,
            'credit',
            $details
        );
    }

    public static function debit(
        int $userId,
        string $walletType,
        string $amount,
        string $entryType,
        string $transactionKey,
        array $details = []
    ): void {
        self::changeBalance(
            $userId,
            $walletType,
            $amount,
            $entryType,
            $transactionKey,
            'debit',
            $details
        );
    }

    private static function changeBalance(
        int $userId,
        string $walletType,
        string $amount,
        string $entryType,
        string $transactionKey,
        string $direction,
        array $details
    ): void {
        if (!in_array($walletType, self::ALLOWED_WALLETS, true)) {
            throw new InvalidArgumentException('Invalid wallet type.');
        }

        if ($entryType === '' || $transactionKey === '') {
            throw new InvalidArgumentException(
                'Entry type and transaction key are required.'
            );
        }

        if (strlen($transactionKey) > 255) {
            throw new InvalidArgumentException(
                'Transaction key is too long.'
            );
        }

        if (!preg_match('/^\d+(\.\d{1,8})?$/', $amount)) {
            throw new InvalidArgumentException('Invalid amount format.');
        }

        if (bccomp($amount, '0', 8) <= 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }

        DB::transaction(function () use (
            $userId,
            $walletType,
            $amount,
            $entryType,
            $transactionKey,
            $direction,
            $details
        ) {
            $user = User::whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = DB::table('financial_ledger')
                ->where('transaction_key', $transactionKey)
                ->first();

            if ($existing) {
                throw new RuntimeException(
                    'Duplicate transaction key.'
                );
            }

            $before = (string) $user->{$walletType};

            if ($direction === 'credit') {
                $after = bcadd($before, $amount, 8);
            } else {
                if (bccomp($before, $amount, 8) < 0) {
                    throw new RuntimeException(
                        'Insufficient wallet balance.'
                    );
                }

                $after = bcsub($before, $amount, 8);
            }

            $user->{$walletType} = $after;
            $user->save();

            DB::table('financial_ledger')->insert([
                'user_id' => $user->id,
                'entry_type' => $entryType,
                'wallet_type' => $walletType,
                'amount' => $direction === 'credit'
                    ? $amount
                    : '-' . $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'from_user_id' => $details['from_user_id'] ?? null,
                'reference_user_id' => $details['reference_user_id'] ?? null,
                'level' => $details['level'] ?? null,
                'reference_type' => $details['reference_type'] ?? null,
                'reference_id' => $details['reference_id'] ?? null,
                'transaction_key' => $transactionKey,
                'status' => 'completed',
                'description' => $details['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }
}
