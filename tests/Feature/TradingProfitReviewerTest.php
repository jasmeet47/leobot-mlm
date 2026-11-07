<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingProfitService;
use App\Services\StakingReconciliationService;
use App\Services\StakingService;
use App\Services\TradingProfitReviewerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class TradingProfitReviewerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reviewer;

    private User $staker;

    protected function setUp(): void
    {
        parent::setUp();
        config(['leobot.production_financial_approval' => true, 'leobot.staking_profit_enabled' => true, 'leobot.staking_enabled' => true]);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->reviewer = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);
        $this->staker = User::factory()->create(['role' => 'member', 'status' => 'active', 'activation_balance' => '100']);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        (new StakingService)->create($this->staker->id, '100', 'reviewer_seed_stake_01', 'activation_balance');
    }

    private function statement(): int
    {
        return StakingProfitService::submit($this->admin, '100', 'REVIEW-STATEMENT-001', [$this->staker->id => '100']);
    }

    private function digest(int $id): string
    {
        return TradingProfitReviewerService::evidenceDigest(DB::table('staking_profit_statements')->find($id), DB::table('staking_profit_allocations')->where('statement_id', $id)->orderBy('user_id')->get());
    }

    private function review(int $id, ?User $reviewer = null): int
    {
        return TradingProfitReviewerService::attest($reviewer ?? $this->reviewer, $id, $this->digest($id));
    }

    private function grant(): void
    {
        TradingProfitReviewerService::setEnabled($this->admin, $this->reviewer->id, true);
    }

    public function test_permission_does_not_promote_or_change_financial_state_and_is_audited_once(): void
    {
        $before = (array) DB::table('users')->find($this->reviewer->id);
        $this->grant();
        $this->grant();
        $this->assertSame($before, (array) DB::table('users')->find($this->reviewer->id));
        $this->assertDatabaseCount('trading_profit_reviewer_audits', 1);
        $this->assertTrue(TradingProfitReviewerService::canReview($this->reviewer->id));
        TradingProfitReviewerService::setEnabled($this->admin, $this->reviewer->id, false);
        $this->assertFalse(TradingProfitReviewerService::canReview($this->reviewer->id));
        $this->assertDatabaseCount('trading_profit_reviewer_audits', 2);
    }

    public function test_member_cannot_assign_permissions_even_with_a_stale_admin_model(): void
    {
        DB::table('users')->where('id', $this->admin->id)->update(['role' => 'member']);
        $this->expectException(AuthorizationException::class);
        $this->grant();
    }

    public function test_blocked_member_cannot_be_granted_review_access(): void
    {
        $this->reviewer->forceFill(['is_blocked' => true])->save();
        $this->expectExceptionMessage('An existing login-eligible unblocked member is required.');
        $this->grant();
    }

    public function test_inactive_registered_reviewer_keeps_financial_ineligibility(): void
    {
        $this->reviewer->forceFill(['status' => 'inactive'])->save();
        $this->grant();
        $this->review($this->statement());
        $this->assertSame('inactive', $this->reviewer->fresh()->status);
        $this->expectExceptionMessage('Member not eligible');
        (new StakingService)->create($this->reviewer->id, '1', 'reviewer_cannot_activate_01');
    }

    public function test_review_queue_is_permission_scoped_and_does_not_expose_admin_controls(): void
    {
        $this->actingAs($this->reviewer)->get(route('reviewer.trading-profit.index'))->assertForbidden();
        $this->grant();
        $this->get(route('reviewer.trading-profit.index'))->assertOk()->assertSee('Independent Trading-Profit Review');
        foreach (['/admin/dashboard', '/admin/deposit-control', '/admin/withdrawals', '/admin/staking'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('admin.staking.profit.reviewers'), ['username' => $this->staker->username, 'enabled' => 1, 'trusted_independent_person' => 1])->assertForbidden();
        $this->post(route('admin.staking.profit.approve', 1), ['verified_funds_received' => 1])->assertForbidden();
    }

    public function test_review_is_an_immutable_idempotent_attestation_and_never_pays_money(): void
    {
        $this->grant();
        $id = $this->statement();
        $before = DB::table('financial_ledger')->count();
        $review = $this->review($id);
        $this->assertSame($review, $this->review($id));
        $this->assertDatabaseCount('staking_profit_reviews', 1);
        $this->assertDatabaseCount('profit_sharing_fundings', 0);
        $this->assertSame($before, DB::table('financial_ledger')->count());
        $this->assertSame('0.00000000', $this->staker->fresh()->available_balance);
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'independently_verified', 'verified_by' => $this->reviewer->id]);
    }

    public function test_review_form_requires_explicit_confirmation(): void
    {
        $this->grant();
        $id = $this->statement();
        $this->actingAs($this->reviewer)->post(route('reviewer.trading-profit.attest', $id), [])->assertSessionHasErrors('verified_statement_and_received_funds');
        $this->assertDatabaseCount('staking_profit_reviews', 0);
    }

    public function test_beneficiary_cannot_review_their_own_profit(): void
    {
        TradingProfitReviewerService::setEnabled($this->admin, $this->staker->id, true);
        $this->expectException(AuthorizationException::class);
        $this->review($this->statement(), $this->staker);
    }

    public function test_owner_cannot_self_approve_without_an_independent_attestation(): void
    {
        $id = $this->statement();
        $this->expectException(AuthorizationException::class);
        StakingProfitService::approve($this->admin, $id);
    }

    public function test_owner_can_reserve_after_independent_review_with_actual_reviewer_and_balanced_journals(): void
    {
        $this->grant();
        $id = $this->statement();
        $this->review($id);
        StakingProfitService::reserveReviewed($this->admin, $id);
        StakingProfitService::reserveReviewed($this->admin, $id);
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'reserved', 'verified_by' => $this->reviewer->id, 'verification_method' => 'independent_member_attestation']);
        $this->assertDatabaseCount('profit_sharing_fundings', 1);
        $this->assertDatabaseHas('profit_sharing_fundings', ['self_amount' => '67.00000000', 'generation_amount' => '30.00000000', 'magic_amount' => '3.00000000']);
        $this->assertSame('member', $this->reviewer->fresh()->role);
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed']);
        $this->assertSame('0.00000000', (string) DB::table('financial_ledger')->whereIn('reference_type', ['staking_profit_statement', 'profit_sharing_funding'])->sum('amount'));
    }

    public function test_revoked_attestation_cannot_fund_and_no_external_receipt_is_created(): void
    {
        $this->grant();
        $id = $this->statement();
        $this->review($id);
        TradingProfitReviewerService::setEnabled($this->admin, $this->reviewer->id, false);
        try {
            StakingProfitService::reserveReviewed($this->admin, $id);
            $this->fail('Revoked reviewer accepted.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('profit_sharing_fundings', 0);
            $this->assertSame(0, DB::table('financial_ledger')->where('reference_type', 'staking_profit_statement')->count());
        }
    }

    public function test_changed_evidence_invalidates_the_review_instead_of_approving_new_values(): void
    {
        $this->grant();
        $id = $this->statement();
        $this->review($id);
        DB::table('staking_profit_statements')->where('id', $id)->update(['evidence_reference' => 'CHANGED-REFERENCE-001']);
        $this->expectExceptionMessage('Reviewed evidence changed; independent reconciliation required.');
        StakingProfitService::reserveReviewed($this->admin, $id);
    }

    public function test_revocation_preserves_historical_approved_financial_proof(): void
    {
        $this->grant();
        $id = $this->statement();
        $this->review($id);
        StakingProfitService::reserveReviewed($this->admin, $id);
        TradingProfitReviewerService::setEnabled($this->admin, $this->reviewer->id, false);
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed']);
        DB::table('staking_profit_reviews')->where('statement_id', $id)->update(['evidence_digest' => str_repeat('0', 64)]);
        $this->assertFalse(StakingReconciliationService::inspect()['accounting_passed']);
    }

    public function test_review_is_disabled_when_financial_gates_are_closed(): void
    {
        $this->grant();
        $id = $this->statement();
        config(['leobot.staking_profit_enabled' => false]);
        $this->expectExceptionMessage('Trading-profit review is disabled.');
        $this->review($id);
    }

    public function test_http_workflow_grants_review_only_then_reserves_after_checked_independent_review(): void
    {
        $id = $this->statement();
        $this->actingAs($this->admin)->from('/admin/staking/profit')->post(route('admin.staking.profit.reviewers'), [
            'username' => $this->reviewer->username, 'enabled' => 1, 'trusted_independent_person' => 1,
        ])->assertRedirect('/admin/staking/profit')->assertSessionHasNoErrors();
        $this->actingAs($this->reviewer)->get(route('reviewer.trading-profit.index'))->assertOk()->assertSee('REVIEW-STATEMENT-001');
        $this->post(route('reviewer.trading-profit.attest', $id), ['verified_statement_and_received_funds' => 1, 'evidence_digest' => $this->digest($id)])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get(route('admin.staking.profit.index'))->assertOk()->assertSee('Verify')->assertDontSee('Independent member reviewers');
        $this->post(route('admin.staking.profit.reserve', $id), ['verified_funds_received' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'reserved', 'verified_by' => $this->reviewer->id]);
        $this->assertSame('member', $this->reviewer->fresh()->role);
    }

    public function test_blocked_reviewer_with_stale_login_cannot_record_a_review(): void
    {
        $this->grant();
        $id = $this->statement();
        DB::table('users')->where('id', $this->reviewer->id)->update(['is_blocked' => true]);
        $this->actingAs($this->reviewer)->post(route('reviewer.trading-profit.attest', $id), ['verified_statement_and_received_funds' => 1, 'evidence_digest' => $this->digest($id)])->assertForbidden();
        $this->assertDatabaseCount('staking_profit_reviews', 0);
    }

    public function test_submitter_cannot_use_self_approval_even_after_someone_else_reviewed(): void
    {
        $this->grant();
        $id = $this->statement();
        $this->review($id);
        $this->expectException(AuthorizationException::class);
        StakingProfitService::approve($this->admin, $id);
    }

    public function test_stale_review_form_cannot_approve_values_that_changed_after_display(): void
    {
        $this->grant();
        $id = $this->statement();
        $digest = $this->digest($id);
        DB::table('staking_profit_statements')->where('id', $id)->update(['evidence_reference' => 'CHANGED-BEFORE-SUBMIT']);
        $this->actingAs($this->reviewer)->post(route('reviewer.trading-profit.attest', $id), [
            'verified_statement_and_received_funds' => 1, 'evidence_digest' => $digest,
        ])->assertSessionHasErrors('review');
        $this->assertDatabaseCount('staking_profit_reviews', 0);
    }

    public function test_renewed_review_preserves_prior_decisions_and_retries_once(): void
    {
        $this->grant();
        $id = $this->statement();
        $this->review($id);
        $other = User::factory()->create(['role' => 'member', 'status' => 'active']);
        TradingProfitReviewerService::setEnabled($this->admin, $other->id, true);
        $this->review($id, $other);
        $last = $this->review($id);
        $this->assertSame($last, $this->review($id));
        $this->assertDatabaseCount('staking_profit_reviews', 3);
        StakingProfitService::reserveReviewed($this->admin, $id);
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'verified_by' => $this->reviewer->id, 'status' => 'reserved']);
    }
}
