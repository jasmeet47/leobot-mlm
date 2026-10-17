<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Selects a verified, member-owned wallet; never accepts an arbitrary payout address. */
final class UsdtVerifiedWallet
{
    public static function forWithdrawal(int $memberId, int $walletId): object
    {
        $wallet = DB::table('usdt_member_wallets')->where('id', $walletId)
            ->where('user_id', $memberId)->lockForUpdate()->first();
        if (!$wallet || $wallet->verified_at === null || $wallet->revoked_at !== null) {
            throw new RuntimeException('A verified, active member-owned USDT wallet is required.');
        }
        UsdtPaymentEvidence::assertAddress((string) $wallet->network, (string) $wallet->address);
        return $wallet;
    }

    public static function lockWithdrawal(int $withdrawalId, int $memberId, int $walletId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Wallet lock requires a database transaction.');
        }
        $wallet = self::forWithdrawal($memberId, $walletId);
        $affected = DB::table('withdrawal_requests')->where('id', $withdrawalId)
            ->where('user_id', $memberId)->where('status', 'requested')
            ->whereNull('payment_network')->whereNull('locked_payment_address')
            ->update([
                'usdt_member_wallet_id' => $walletId,
                'payment_network' => $wallet->network,
                'locked_payment_address' => $wallet->address,
                'updated_at' => now(),
            ]);
        if ($affected !== 1) {
            throw new RuntimeException('Withdrawal wallet is already locked or withdrawal is ineligible.');
        }
    }
}
