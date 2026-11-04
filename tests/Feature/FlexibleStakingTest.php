<?php

namespace Tests\Feature;

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use App\Services\StakingPrincipalService;
use App\Services\StakingService;
use App\Services\VerifiedDepositService;
use App\Services\WithdrawalPaymentConfirmationService;
use App\Services\WithdrawalRequestService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class FlexibleStakingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    private int $positionId;

    private int $walletId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['leobot_deposits.receiving_addresses.BEP20' => '0x'.str_repeat('2', 40),
            'leobot_deposits.receiving_addresses.TRC20' => 'T'.str_repeat('2', 33)]);
        $incomingChain = $this->createStub(UsdtChainVerifier::class);
        $incomingChain->method('verified')->willReturn(true);
        $this->app->instance(UsdtChainVerifier::class, $incomingChain);

        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true,
            'leobot.deposits_enabled' => true, 'leobot.withdrawals_enabled' => true,
            'leobot.withdrawal_settings_write_enabled' => true, 'leobot_withdrawal.external_payment_verification_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);
        $deposit = VerifiedDepositService::submit($this->member->id, '200', 'BEP20', '0x'.str_repeat('a', 64), 'deposit_for_staking');
        VerifiedDepositService::approve($this->admin->id, $deposit, 'external-deposit-review');
        $this->positionId = (new StakingService)->create($this->member->id, '100', 'flexible_stake_0001', 'activation_balance');
        $this->walletId = $this->wallet($this->member, 'BEP20');
        WithdrawalRequestService::setPrincipalFeePercent($this->admin, '10');
        WithdrawalRequestService::setControls($this->admin, true, true, true);
    }

    private function wallet(User $member, string $network): int
    {
        return DB::table('usdt_member_wallets')->insertGetId(['user_id' => $member->id, 'network' => $network,
            'address' => $network === 'BEP20' ? '0x'.str_repeat('1', 40) : 'T'.str_repeat('1', 33),
            'verified_at' => now(), 'verification_method' => 'test_fixture', 'proof_digest' => str_repeat('a', 64),
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function request(string $amount = '40', string $key = 'principal_request_01'): int
    {
        return StakingPrincipalService::request($this->member, $this->positionId, $amount, $key, $this->walletId, '10');
    }

    private function paymentService(bool $verified): WithdrawalPaymentConfirmationService
    {
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn($verified);

        return new WithdrawalPaymentConfirmationService($chain);
    }

    public function test_verified_deposit_can_be_staked_and_principal_requested_immediately(): void
    {
        $id = $this->request();
        $this->assertSame('100.00000000', $this->member->fresh()->activation_balance);
        $this->assertSame('0.00000000', $this->member->fresh()->available_balance);
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $id, 'wallet_type' => 'staking_principal',
            'gross_amount' => '40.00000000', 'fee_amount' => '4.00000000', 'net_amount' => '36.00000000', 'status' => 'requested']);
        $this->assertDatabaseHas('staking_positions', ['id' => $this->positionId, 'withdrawn_amount' => '40.00000000']);
        $this->assertSame('0.00000000', (string) DB::table('financial_ledger')->where('reference_type', 'staking_position')
            ->where('reference_id', $this->positionId)->whereIn('entry_type', ['staking_principal', 'staking_principal_reserved'])->sum('amount'));
    }

    public function test_staking_off_does_not_lock_existing_principal(): void
    {
        (new StakingService)->toggle($this->admin, false);
        config(['leobot.staking_enabled' => false]);
        $this->request('100');
        $this->assertDatabaseHas('staking_positions', ['id' => $this->positionId, 'withdrawn_amount' => '100.00000000']);
    }

    public function test_retry_and_fee_changes_preserve_original_request_snapshot(): void
    {
        $id = $this->request();
        WithdrawalRequestService::setPrincipalFeePercent($this->admin, '25');
        $this->assertSame($id, $this->request());
        $this->assertDatabaseCount('withdrawal_requests', 1);
        $this->assertDatabaseHas('staking_principal_withdrawals', ['withdrawal_request_id' => $id, 'fee_percent_snapshot' => '10.000000000']);
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $id, 'net_amount' => '36.00000000']);
    }

    public function test_changed_fee_requires_member_to_review_new_quote(): void
    {
        WithdrawalRequestService::setPrincipalFeePercent($this->admin, '25');
        $this->expectExceptionMessage('The fee changed');
        $this->request();
    }

    public function test_partial_requests_cannot_exceed_unreserved_principal(): void
    {
        $this->request('70');
        try {
            $this->request('40', 'principal_request_02');
            $this->fail('Principal overdraw accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('Insufficient unreserved principal.', $e->getMessage());
        }
        $this->assertDatabaseCount('withdrawal_requests', 1);
        $this->assertDatabaseHas('staking_positions', ['id' => $this->positionId, 'withdrawn_amount' => '70.00000000']);
    }

    public function test_rejection_restores_principal_once_without_creating_rewards(): void
    {
        $id = $this->request();
        WithdrawalRequestService::reject($this->admin, $id);
        $this->assertDatabaseHas('staking_positions', ['id' => $this->positionId, 'withdrawn_amount' => '0.00000000']);
        $this->assertSame('0.00000000', $this->member->fresh()->available_balance);
        $this->assertDatabaseHas('financial_ledger', ['transaction_key' => 'staking-principal:refund:'.$id, 'amount' => '40.00000000']);
        $this->expectExceptionMessage('Only unpaid withdrawals can be rejected.');
        WithdrawalRequestService::reject($this->admin, $id);
    }

    public function test_ledger_failure_rolls_back_principal_hold_and_request(): void
    {
        DB::statement("ALTER TABLE financial_ledger ADD CONSTRAINT test_reject_principal CHECK (wallet_type <> 'staking_principal') NOT VALID");
        try {
            try {
                $this->request();
                $this->fail('Ledger failure expected');
            } catch (QueryException) {
                $this->assertDatabaseCount('withdrawal_requests', 0);
                $this->assertDatabaseCount('staking_principal_withdrawals', 0);
                $this->assertDatabaseHas('staking_positions', ['id' => $this->positionId, 'withdrawn_amount' => '0.00000000']);
            }
        } finally {
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT test_reject_principal');
        }
    }

    public function test_trc20_net_amount_is_representable_and_rounding_is_in_fee(): void
    {
        $wallet = $this->wallet($this->member, 'TRC20');
        $id = StakingPrincipalService::request($this->member, $this->positionId, '10.12345678', 'principal_trc20_01', $wallet, '10');
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $id, 'payment_network' => 'TRC20',
            'net_amount' => '9.11111100', 'fee_amount' => '1.01234578']);
    }

    public function test_other_members_wallet_cannot_receive_principal(): void
    {
        $other = User::factory()->create();
        $wallet = $this->wallet($other, 'BEP20');
        $this->expectExceptionMessage('verified, active member-owned USDT wallet');
        StakingPrincipalService::request($this->member, $this->positionId, '40', 'principal_request_01', $wallet, '10');
    }

    public function test_payment_processing_blocks_refunds_and_fee_is_booked_only_after_chain_verification(): void
    {
        $id = $this->request();
        WithdrawalRequestService::approve($this->admin, $id);
        $service = $this->paymentService(false);
        $service->beginPayment($this->admin, $id);
        try {
            WithdrawalRequestService::reject($this->admin, $id);
            $this->fail('Processing payment was refunded');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('withdrawal_requests', ['id' => $id, 'status' => 'payment_processing']);
        }
        $hash = '0x'.str_repeat('b', 64);
        $service->submitPayment($this->admin, $id, 'BEP20', $hash);
        try {
            $service->confirm($this->admin, $id, 'BEP20', $hash);
            $this->fail('Unverified transfer accepted');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('withdrawal_requests', ['id' => $id, 'status' => 'payment_submitted']);
            $this->assertDatabaseHas('company_wallet', ['id' => 1, 'balance' => '0.00000000']);
        }
        $chain = $this->createMock(UsdtChainVerifier::class);
        $chain->expects($this->once())->method('verified')->with('BEP20', $hash, '0x'.str_repeat('1', 40), '36.00000000')->willReturn(true);
        (new WithdrawalPaymentConfirmationService($chain))->confirm($this->admin, $id, 'BEP20', $hash);
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $id, 'status' => 'paid']);
        $this->assertDatabaseHas('company_wallet', ['id' => 1, 'balance' => '4.00000000']);
        $this->assertDatabaseHas('financial_ledger', ['transaction_key' => 'staking:withdrawal-fee:'.$id, 'amount' => '4.00000000']);
        $this->assertSame('0.00000000', $this->member->fresh()->available_balance);
    }

    public function test_member_can_use_staking_page_without_seeing_other_members_positions(): void
    {
        $this->actingAs($this->member)->get(route('member.staking.index'))->assertOk()->assertSee('Flexible USDT Staking')->assertSee('Stake #'.$this->positionId);
        $this->actingAs($this->member)->get(route('admin.staking.profit.index'))->assertForbidden();
    }

    public function test_missing_opening_journal_cannot_release_unbacked_principal(): void
    {
        DB::table('financial_ledger')->where('transaction_key', 'staking:principal:flexible_stake_0001')->delete();
        $this->expectExceptionMessage('Principal opening journals require independent reconciliation.');
        $this->request();
    }

    public function test_production_staking_is_rejected_when_payout_verification_is_unavailable(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['leobot_usdt_rpc.enabled' => false]);
        $this->expectExceptionMessage('Production staking requires an operational principal payout policy');
        (new StakingService)->create($this->member->id, '10', 'production_stake_0001', 'activation_balance');
    }

    public function test_disabled_deposits_do_not_display_receiving_addresses_or_invite_funding(): void
    {
        config(['leobot.deposits_enabled' => false]);
        $this->actingAs($this->member)->get(route('member.deposit-transfer.index'))->assertOk()
            ->assertSee('Deposits are currently disabled.')->assertDontSee('0x'.str_repeat('2', 40));
    }
}
