<?php

namespace Tests\Feature;

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use App\Services\VerifiedDepositService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class VerifiedDepositSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['leobot_deposits.receiving_addresses.BEP20' => '0x'.str_repeat('2', 40),
            'leobot_deposits.receiving_addresses.TRC20' => 'T'.str_repeat('2', 33)]);
        $incomingChain = $this->createStub(UsdtChainVerifier::class);
        $incomingChain->method('verified')->willReturn(true);
        $this->app->instance(UsdtChainVerifier::class, $incomingChain);

        config(['leobot.deposits_enabled' => true]);
    }

    public function test_same_chain_payment_cannot_be_submitted_twice_with_different_case(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $hash = '0x'.str_repeat('ab', 32);
        $id = VerifiedDepositService::submit($member->id, '10', 'BEP20', strtoupper($hash), 'deposit_request_1');
        $this->assertSame($id, VerifiedDepositService::submit($member->id, '10', 'BEP20', $hash, 'deposit_request_1'));
        try {
            VerifiedDepositService::submit($member->id, '10', 'BEP20', $hash, 'deposit_request_2');
            $this->fail('Duplicate chain payment accepted');
        } catch (QueryException) {
            $this->assertDatabaseCount('member_deposit_requests', 1);
        }
    }

    public function test_approval_credits_once_and_refuses_inactive_admin(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'inactive']);
        $id = VerifiedDepositService::submit($member->id, '10', 'TRC20', str_repeat('a', 64), 'deposit_request_1');
        try {
            VerifiedDepositService::approve($admin->id, $id, 'review-reference-1');
            $this->fail('Inactive admin approved payment');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('financial_ledger', 0);
        }
        $admin->forceFill(['status' => 'active'])->save();
        VerifiedDepositService::approve($admin->id, $id, 'review-reference-1');
        try {
            VerifiedDepositService::approve($admin->id, $id, 'review-reference-2');
            $this->fail('Repeated approval accepted');
        } catch (\RuntimeException) {
            $this->assertSame('10.00000000', $member->fresh()->activation_balance);
            $this->assertDatabaseCount('financial_ledger', 1);
        }
    }

    public function test_ledger_failure_rolls_back_deposit_approval_and_balance(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $id = VerifiedDepositService::submit($member->id, '10', 'TRC20', str_repeat('a', 64), 'deposit_request_1');
        DB::statement('ALTER TABLE financial_ledger ADD CONSTRAINT test_reject_credit CHECK (amount <= 0) NOT VALID');
        try {
            try {
                VerifiedDepositService::approve($admin->id, $id, 'review-reference-1');
                $this->fail('Ledger failure expected');
            } catch (QueryException) {
                $this->assertSame('0.00000000', $member->fresh()->activation_balance);
                $this->assertDatabaseHas('member_deposit_requests', ['id' => $id, 'status' => 'pending', 'reviewed_by' => null]);
                $this->assertDatabaseCount('financial_ledger', 0);
            }
        } finally {
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT test_reject_credit');
        }
    }

    public function test_unconfirmed_chain_payment_cannot_credit_a_wallet(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $hash = '0x'.str_repeat('a', 64);
        $id = VerifiedDepositService::submit($member->id, '10', 'BEP20', $hash, 'deposit_request_1');
        $chain = $this->createMock(UsdtChainVerifier::class);
        $chain->expects($this->once())->method('verified')->with('BEP20', $hash, '0x'.str_repeat('2', 40), '10.00000000')->willReturn(false);
        $this->app->instance(UsdtChainVerifier::class, $chain);
        try {
            VerifiedDepositService::approve($admin->id, $id, 'review-reference-1');
            $this->fail('Unconfirmed deposit credited');
        } catch (\RuntimeException) {
            $this->assertSame('0.00000000', $member->fresh()->activation_balance);
            $this->assertDatabaseHas('member_deposit_requests', ['id' => $id, 'status' => 'pending']);
            $this->assertDatabaseCount('financial_ledger', 0);
        }
    }

    public function test_changed_deposit_details_invalidate_an_external_verification(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $id = VerifiedDepositService::submit($member->id, '10', 'BEP20', '0x'.str_repeat('a', 64), 'deposit_request_1');
        $chain = $this->createMock(UsdtChainVerifier::class);
        $chain->expects($this->once())->method('verified')->willReturnCallback(function () use ($id): bool {
            DB::table('member_deposit_requests')->where('id', $id)->update(['amount' => '11']);

            return true;
        });
        $this->app->instance(UsdtChainVerifier::class, $chain);
        try {
            VerifiedDepositService::approve($admin->id, $id, 'review-reference-1');
            $this->fail('Stale proof credited');
        } catch (\RuntimeException $e) {
            $this->assertSame('Deposit details changed during verification.', $e->getMessage());
            $this->assertSame('0.00000000', $member->fresh()->activation_balance);
            $this->assertDatabaseCount('financial_ledger', 0);
        }
    }
}
