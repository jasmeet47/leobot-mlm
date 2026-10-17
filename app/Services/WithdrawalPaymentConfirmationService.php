<?php

namespace App\Services;

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** No HTTP route, no transfer and no verifier binding: intentionally fail-closed. */
final class WithdrawalPaymentConfirmationService
{
    public function __construct(private UsdtChainVerifier $chain) {}

    public static function assertPayable(object $withdrawal): void
    {
        if (($withdrawal->status ?? null) !== 'approved_awaiting_payment') {
            throw new RuntimeException('Only approved unpaid withdrawals can be confirmed.');
        }
        if (empty($withdrawal->locked_payment_address) ||
            !in_array($withdrawal->payment_network ?? null, ['TRC20', 'BEP20'], true) ||
            empty($withdrawal->usdt_member_wallet_id)) {
            throw new RuntimeException('Withdrawal has no locked verified USDT recipient.');
        }
        if (!preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', (string) ($withdrawal->net_amount ?? '')) ||
            bccomp((string) $withdrawal->net_amount, '0', 8) <= 0) {
            throw new RuntimeException('Invalid withdrawal net amount.');
        }
    }

    public function confirm(User $admin, int $withdrawalId, string $network, string $txHash): void
    {
        // Separate OFF switch. Never turn on as part of installing this patch.
        if (config('leobot_withdrawal.external_payment_verification_enabled', false) !== true ||
            config('leobot.withdrawals_enabled', false) !== true ||
            (app()->environment('production') && config('leobot.production_financial_approval', false) !== true)) {
            throw new RuntimeException('External payment confirmation is disabled.');
        }
        $settings = DB::table('withdrawal_settings')->where('id', 1)->first();
        if (!$settings || !in_array(strtolower((string) $settings->admin_review_enabled), ['1','t','true','on'], true)) {
            throw new RuntimeException('Admin review is disabled.');
        }
        $actor = DB::table('users')->where('id', $admin->getKey())->first();
        if (!$actor || !in_array($actor->role, ['admin', 'super_admin'], true) ||
            $actor->status !== 'active' ||
            in_array(strtolower((string) ($actor->is_blocked ?? false)), ['1','t','true'], true)) {
            throw new AuthorizationException('Active unblocked administrator required.');
        }
        $withdrawal = DB::table('withdrawal_requests')->where('id', $withdrawalId)->first();
        if (!$withdrawal) throw new RuntimeException('Withdrawal not found.');
        self::assertPayable($withdrawal);
        if ($network !== $withdrawal->payment_network) throw new RuntimeException('Network mismatch.');
        if ((int) $withdrawal->user_id === (int) $actor->id) throw new AuthorizationException('Self-confirmation denied.');

        // Trusted verifier checks chain ID, canonical USDT token, successful transfer,
        // exact amount, recipient and finality. Never trust a submitted receipt.
        $hash = (new UsdtPaymentVerificationPolicy($this->chain))->assertVerified(
            $network, $txHash, (string) $withdrawal->locked_payment_address,
            (string) $withdrawal->locked_payment_address, (string) $withdrawal->net_amount
        );

        DB::transaction(function () use ($admin, $withdrawalId, $network, $hash, $withdrawal): void {
            // Recheck financial authorization after external chain verification.
            if (config('leobot_withdrawal.external_payment_verification_enabled', false) !== true ||
                config('leobot.withdrawals_enabled', false) !== true ||
                (app()->environment('production') && config('leobot.production_financial_approval', false) !== true)) {
                throw new RuntimeException('External payment confirmation was disabled.');
            }
            $currentSettings = DB::table('withdrawal_settings')->where('id', 1)->lockForUpdate()->first();
            if (!$currentSettings ||
                !in_array(strtolower((string) $currentSettings->admin_review_enabled), ['1','t','true','on'], true)) {
                throw new RuntimeException('Admin review was disabled.');
            }
            $actor = DB::table('users')->where('id', $admin->getKey())->lockForUpdate()->first();
            if (!$actor || !in_array($actor->role, ['admin','super_admin'], true) ||
                $actor->status !== 'active' ||
                in_array(strtolower((string) ($actor->is_blocked ?? false)), ['1','t','true'], true)) {
                throw new AuthorizationException('Administrator no longer authorized.');
            }
            $w = DB::table('withdrawal_requests')->where('id', $withdrawalId)->lockForUpdate()->first();
            if (!$w) throw new RuntimeException('Withdrawal not found.');
            self::assertPayable($w);
            if ($w->payment_network !== $network) throw new RuntimeException('Network changed.');
            if ((int) $w->user_id === (int) $actor->id) throw new AuthorizationException('Self-confirmation denied.');
            // Reject a stale external verification if any payment-critical field changed.
            if ($w->locked_payment_address !== $withdrawal->locked_payment_address ||
                bccomp((string) $w->net_amount, (string) $withdrawal->net_amount, 8) !== 0 ||
                (int) $w->usdt_member_wallet_id !== (int) $withdrawal->usdt_member_wallet_id) {
                throw new RuntimeException('Payment details changed during verification.');
            }
            DB::table('withdrawal_payment_evidence')->insert([
                'withdrawal_request_id' => $withdrawalId,
                'network' => $network,
                'tx_hash' => $hash,
                'recipient' => $w->locked_payment_address,
                'amount' => $w->net_amount,
                'verified_by' => $actor->id,
                'verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('withdrawal_requests')->where('id', $withdrawalId)->update([
                'status' => 'paid',
                'external_payment_reference' => $network . ':' . $hash,
                'externally_paid_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('withdrawal_events')->insert([
                'withdrawal_request_id' => $withdrawalId,
                'actor_user_id' => $actor->id,
                'event_type' => 'verified_on_chain_paid',
                'description' => 'Verified ' . $network . ' tx ' . $hash,
                'created_at' => now(),
            ]);
        }, 3);
    }
}
