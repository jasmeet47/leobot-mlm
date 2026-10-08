<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WalletService
{
    private const SCALE = 8;

    private const MAX_BALANCE = '999999999999.99999999';

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
        if ($userId <= 0) {
            throw new InvalidArgumentException(
                'Invalid user ID.'
            );
        }

        if (!in_array($walletType, self::ALLOWED_WALLETS, true)) {
            throw new InvalidArgumentException(
                'Invalid wallet type.'
            );
        }

        if (
            trim($entryType) === '' ||
            trim($transactionKey) === ''
        ) {
            throw new InvalidArgumentException(
                'Entry type and transaction key are required.'
            );
        }

        if (
            strlen($entryType) > 255 ||
            strlen($transactionKey) > 255
        ) {
            throw new InvalidArgumentException(
                'Entry type or transaction key is too long.'
            );
        }

        if (!preg_match('/^\d+(\.\d{1,8})?$/D', $amount)) {
            throw new InvalidArgumentException(
                'Invalid amount format.'
            );
        }

        if (bccomp($amount, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException(
                'Amount must be positive.'
            );
        }

        if (
            bccomp(
                $amount,
                self::MAX_BALANCE,
                self::SCALE
            ) > 0
        ) {
            throw new InvalidArgumentException(
                'Amount exceeds wallet limit.'
            );
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
                ->exists();

            if ($existing) {
                throw new RuntimeException(
                    'Duplicate transaction key.'
                );
            }

            $before = (string) $user->{$walletType};

            if (
                !preg_match('/^\d+(\.\d{1,8})?$/D', $before) ||
                bccomp($before, '0', self::SCALE) < 0 ||
                bccomp(
                    $before,
                    self::MAX_BALANCE,
                    self::SCALE
                ) > 0
            ) {
                throw new RuntimeException(
                    'Invalid existing wallet balance.'
                );
            }

            if ($direction === 'credit') {
                $after = bcadd(
                    $before,
                    $amount,
                    self::SCALE
                );

                if (
                    bccomp(
                        $after,
                        self::MAX_BALANCE,
                        self::SCALE
                    ) > 0
                ) {
                    throw new RuntimeException(
                        'Wallet balance limit exceeded.'
                    );
                }
            } else {
                if (
                    bccomp(
                        $before,
                        $amount,
                        self::SCALE
                    ) < 0
                ) {
                    throw new RuntimeException(
                        'Insufficient wallet balance.'
                    );
                }

                $after = bcsub(
                    $before,
                    $amount,
                    self::SCALE
                );
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
