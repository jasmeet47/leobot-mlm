<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WalletSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_and_debit_reconcile_exactly_with_ledger(): void
    {
        $member = User::factory()->create(['available_balance' => '0']);
        WalletService::credit($member->id, 'available_balance', '0.00000002', 'test_credit', 'wallet-credit-1');
        WalletService::debit($member->id, 'available_balance', '0.00000001', 'test_debit', 'wallet-debit-1');
        $this->assertSame('0.00000001', $member->fresh()->available_balance);
        $this->assertDatabaseHas('financial_ledger', ['transaction_key' => 'wallet-credit-1', 'balance_before' => '0.00000000', 'balance_after' => '0.00000002']);
        $this->assertDatabaseHas('financial_ledger', ['transaction_key' => 'wallet-debit-1', 'amount' => '-0.00000001', 'balance_after' => '0.00000001']);
    }

    public function test_duplicate_credit_cannot_create_money(): void
    {
        $member = User::factory()->create(['available_balance' => '0']);
        WalletService::credit($member->id, 'available_balance', '25', 'test_credit', 'wallet-credit-1');
        try {
            WalletService::credit($member->id, 'available_balance', '25', 'test_credit', 'wallet-credit-1');
            $this->fail('Duplicate credit accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('Duplicate transaction key.', $e->getMessage());
        }
        $this->assertSame('25.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseCount('financial_ledger', 1);
    }

    public function test_credit_overflow_leaves_balance_and_ledger_unchanged(): void
    {
        $member = User::factory()->create(['available_balance' => '999999999999.99999999']);
        try {
            WalletService::credit($member->id, 'available_balance', '0.00000001', 'test_credit', 'wallet-credit-1');
            $this->fail('Overflow accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('Wallet balance limit exceeded.', $e->getMessage());
        }
        $this->assertSame('999999999999.99999999', $member->fresh()->available_balance);
        $this->assertDatabaseCount('financial_ledger', 0);
    }
}
