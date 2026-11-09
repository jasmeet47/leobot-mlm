<?php

namespace Tests\Feature;

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use App\Services\ReadOnlyUsdtChainVerifier;
use App\Services\VerifiedDepositService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function test_missing_on_chain_transaction_shows_specific_pending_feedback_and_never_credits(): void
    {
        config(['leobot_usdt_rpc.enabled' => true, 'leobot_usdt_rpc.bsc_rpc_url' => 'https://rpc.example.test']);
        Http::preventStrayRequests();
        Http::fake(fn ($request) => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => $request['method'] === 'eth_chainId' ? '0x38' : ($request['method'] === 'eth_blockNumber' ? '0x100' : null)]));
        $this->app->instance(UsdtChainVerifier::class, new ReadOnlyUsdtChainVerifier);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $hash = '0x'.str_repeat('d', 64);
        $id = VerifiedDepositService::submit($member->id, '50', 'BEP20', $hash, 'deposit_missing_tx_fixture');
        $this->actingAs($admin)->from(route('admin.deposit-review.index'))->post(route('admin.deposit-review.approve', $id))
            ->assertRedirect(route('admin.deposit-review.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('deposit_verification_error', fn ($message) => str_contains($message, 'Transaction not found on BNB Smart Chain'));
        $this->withCookie(config('session.cookie').'_admin', session()->getId())->get(route('admin.deposit-review.index'))->assertOk()
            ->assertSee('Deposit not approved')->assertSee('Transaction not found on BNB Smart Chain')
            ->assertSee('View on BscScan')->assertDontSee('Your changes could not be saved')->assertDontSee('data-lf-toast="success"', false);
        $this->assertDatabaseHas('member_deposit_requests', ['id' => $id, 'status' => 'pending', 'reviewed_by' => null]);
        $this->assertSame('0.00000000', $member->fresh()->activation_balance);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_unreachable_chain_provider_shows_safe_retry_feedback_without_credit_or_secret_url(): void
    {
        config(['leobot_usdt_rpc.enabled' => true, 'leobot_usdt_rpc.bsc_rpc_url' => 'https://rpc.example.test/private-key-not-for-ui']);
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('secret upstream endpoint detail'));
        $this->app->instance(UsdtChainVerifier::class, new ReadOnlyUsdtChainVerifier);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $id = VerifiedDepositService::submit($member->id, '50', 'BEP20', '0x'.str_repeat('e', 64), 'deposit_offline_tx_fixture');
        $this->actingAs($admin)->post(route('admin.deposit-review.approve', $id))->assertSessionHas('deposit_verification_error', fn ($message) => str_contains($message, 'temporarily unavailable') && ! str_contains($message, 'secret') && ! str_contains($message, 'private-key'));
        $this->assertDatabaseHas('member_deposit_requests', ['id' => $id, 'status' => 'pending']);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    private function surplusReceipt(string $hash, string $recipient): void
    {
        config(['leobot_usdt_rpc.enabled' => true, 'leobot_usdt_rpc.bsc_rpc_url' => 'https://rpc.example.test']);
        $receipt = ['status' => '0x1', 'blockHash' => '0x'.str_repeat('c', 64), 'blockNumber' => '0x64', 'transactionHash' => $hash,
            'logs' => [['address' => '0x55d398326f99059ff775485246999027b3197955', 'removed' => false,
                'topics' => ['0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef', '0x'.str_repeat('0', 64), '0x'.str_repeat('0', 24).substr($recipient, 2)],
                'data' => '0x'.str_pad('2b5e4ad178efe1ff2', 64, '0', STR_PAD_LEFT), 'logIndex' => '0x3b']]];
        $responses = ['eth_chainId' => '0x38', 'eth_getTransactionReceipt' => $receipt, 'eth_getTransactionByHash' => ['hash' => $hash, 'blockHash' => $receipt['blockHash'], 'blockNumber' => '0x64'], 'eth_blockNumber' => '0x100'];
        Http::preventStrayRequests();
        Http::fake(fn ($request) => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => $responses[$request['method']] ?? null]));
        $this->app->instance(UsdtChainVerifier::class, new ReadOnlyUsdtChainVerifier);
    }

    public function test_incoming_surplus_credits_requested_fifty_once_and_audits_all_extra_precision(): void
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $hash = '0x'.str_repeat('f', 64);
        $id = VerifiedDepositService::submit($member->id, '50', 'BEP20', $hash, 'surplus_deposit_fixture');
        $this->surplusReceipt($hash, '0x'.str_repeat('2', 40));
        VerifiedDepositService::approve($admin->id, $id);
        $this->assertSame('50.00000000', $member->fresh()->activation_balance);
        $this->assertDatabaseHas('member_deposit_requests', ['id' => $id, 'status' => 'approved', 'amount' => '50']);
        $this->assertDatabaseCount('financial_ledger', 1);
        $audit = DB::table('member_security_audits')->where('action', 'verified_deposit_chain_evidence')->first();
        $proof = json_decode($audit->details, true);
        $this->assertSame($id, $proof['deposit_id']);
        $this->assertSame('50.000279279668961266', $proof['evidence']['received_amount']);
        $this->assertSame('50.00000000', $proof['evidence']['credited_amount']);
        $this->assertSame('0.000279279668961266', $proof['evidence']['uncredited_surplus']);
        try {
            VerifiedDepositService::approve($admin->id, $id);
            $this->fail('A second credit must not succeed.');
        } catch (\RuntimeException) {
            $this->assertSame('50.00000000', $member->fresh()->activation_balance);
            $this->assertDatabaseCount('financial_ledger', 1);
            $this->assertDatabaseCount('member_security_audits', 1);
        }
        $this->actingAs($admin)->get(route('admin.deposit-review.index', ['status' => 'approved']))->assertOk()
            ->assertSee('50.000279279668961266')->assertSee('0.000279279668961266');
    }

    public function test_audit_failure_rolls_back_surplus_credit_and_approval(): void
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $hash = '0x'.str_repeat('1', 64);
        $id = VerifiedDepositService::submit($member->id, '50', 'BEP20', $hash, 'surplus_rollback_fixture');
        $this->surplusReceipt($hash, '0x'.str_repeat('2', 40));
        DB::unprepared("CREATE FUNCTION pg_temp.reject_deposit_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.action='verified_deposit_chain_evidence' THEN RAISE EXCEPTION 'Audit unavailable'; END IF; RETURN NEW; END $$");
        DB::unprepared('CREATE TRIGGER reject_deposit_audit BEFORE INSERT ON member_security_audits FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_deposit_audit()');
        try {
            VerifiedDepositService::approve($admin->id, $id);
            $this->fail('Audit failure must roll back the complete approval.');
        } catch (QueryException) {
            $this->assertSame('0.00000000', $member->fresh()->activation_balance);
            $this->assertDatabaseHas('member_deposit_requests', ['id' => $id, 'status' => 'pending']);
            $this->assertDatabaseCount('financial_ledger', 0);
            $this->assertDatabaseCount('member_security_audits', 0);
        }
    }
}
