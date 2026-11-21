<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GenerationStatusCorrectionService;
use App\Services\IncomeDistributionService;
use App\Services\MemberAccountAdministration;
use App\Services\StakingProfitService;
use App\Services\StakingService;
use App\Services\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class GenerationStatusCorrectionTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        FinancialSeedState::captureIfNeeded();
    }

    protected function afterTruncatingDatabase(): void
    {
        FinancialSeedState::restoreOrCapture();
    }

    private function held(): array
    {
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot.staking_profit_enabled' => true, 'leobot.admin_profit_verification_enabled' => true, 'leobot.self_income_settlement_enabled' => true, 'leobot.generation_income_settlement_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => false]);
        DB::table('generation_pool_settings')->where('id', 1)->update(['generation_enabled' => false]);
        DB::table('generation_qualification_settings')->update(['required_business' => '999999999']);
        DB::table('generation_qualification_settings')->where('unlock_through_level', 1)->update(['required_business' => '0']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $root = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $child = User::factory()->create(['role' => 'member', 'status' => 'active', 'sponsor_user_id' => $root->id]);
        $leaf = User::factory()->create(['role' => 'member', 'status' => 'active', 'sponsor_user_id' => $child->id]);
        foreach ([$root, $child, $leaf] as $member) {
            WalletService::credit($member->id, 'available_balance', '50', 'fixture', 'status-fix:'.$member->id);
            app(StakingService::class)->create($member->id, '50', 'status_correction_stake_'.$member->id);
        }
        DB::table('users')->where('id', $root->id)->update(['status' => 'inactive']);
        $id = IncomeDistributionService::submit($admin, '14', 'GENERATION-STATUS-CORRECTION', 'weekly', now('Asia/Kolkata')->toDateString());
        StakingProfitService::verifyByAdmin($admin, $id);
        IncomeDistributionService::distribute($admin, $id);
        $allocation = DB::table('generation_member_allocations')->where('user_id', $root->id)->where('level_number', 1)->first();

        return [$admin, $root, $allocation];
    }

    public function test_status_repair_pays_originally_unlocked_hold_once_without_unlocking_other_levels(): void
    {
        [$admin,$root,$allocation] = $this->held();
        $before = $root->fresh()->available_balance;
        $pool = DB::table('generation_pool_accounts')->first();
        $locked = DB::table('generation_member_allocations')->where('user_id', $root->id)->where('level_number', 2)->first();
        $id = GenerationStatusCorrectionService::settle($admin, $allocation->id, (string) $allocation->share_amount);
        $this->assertSame($id, GenerationStatusCorrectionService::settle($admin, $allocation->id, (string) $allocation->share_amount));
        $this->assertSame('active', $root->fresh()->status);
        $this->assertSame('member', $root->fresh()->role);
        $this->assertSame(bcadd($before, (string) $allocation->share_amount, 8), $root->fresh()->available_balance);
        $this->assertEquals($locked, DB::table('generation_member_allocations')->find($locked->id));
        $this->assertDatabaseHas('financial_ledger', ['user_id' => $root->id, 'entry_type' => 'generation_income_settlement', 'level' => 1, 'amount' => $allocation->share_amount]);
        $after = DB::table('generation_pool_accounts')->first();
        $this->assertSame(bcsub((string) $pool->balance, (string) $allocation->share_amount, 8), (string) $after->balance);
        $this->assertSame(bcsub((string) $pool->held_balance, (string) $allocation->share_amount, 8), (string) $after->held_balance);
        $audit = json_decode(DB::table('member_security_audits')->where('action', 'generation_inactive_hold_corrected')->value('details'), true);
        $this->assertSame('member_inactive', $audit['original_allocation']['qualification_reason']);
        $this->assertSame($allocation->share_amount, $audit['original_allocation']['share_amount']);
    }

    public function test_locked_level_and_wrong_amount_are_rejected_without_writes(): void
    {
        [$admin,$root,$allocation] = $this->held();
        $locked = DB::table('generation_member_allocations')->where('user_id', $root->id)->where('level_number', 2)->first();
        $before = DB::table('financial_ledger')->orderBy('id')->get()->toJson();
        foreach ([[$locked->id, $locked->share_amount], [$allocation->id, '999']] as [$id,$amount]) {
            try {
                GenerationStatusCorrectionService::settle($admin, $id, $amount);
                $this->fail('Invalid correction accepted.');
            } catch (\RuntimeException) {
                $this->assertSame($before, DB::table('financial_ledger')->orderBy('id')->get()->toJson());
            }
        }
        $this->assertSame('inactive', $root->fresh()->status);
    }

    public function test_manual_deactivation_and_non_admin_cannot_be_overridden(): void
    {
        [$admin,$root,$allocation] = $this->held();
        DB::table('users')->where('id', $root->id)->update(['status' => 'active']);
        MemberAccountAdministration::status($admin, $root->id, 'inactive', 'active');
        $before = DB::table('financial_ledger')->count();
        foreach ([$root, $admin] as $actor) {
            try {
                GenerationStatusCorrectionService::settle($actor, $allocation->id, $allocation->share_amount);
                $this->fail('Unauthorized correction accepted.');
            } catch (\RuntimeException) {
                $this->assertSame($before, DB::table('financial_ledger')->count());
            }
        }
        $this->assertSame('inactive', $root->fresh()->status);
    }

    public function test_payout_audit_failure_rolls_back_status_repair_and_reserve_reclassification(): void
    {
        [$admin,$root,$allocation] = $this->held();
        $before = DB::table('generation_pool_accounts')->first();
        $count = DB::table('financial_ledger')->count();
        DB::unprepared("CREATE FUNCTION pg_temp.reject_status_payout() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Settlement unavailable'; END $$");
        DB::unprepared('CREATE TRIGGER reject_status_payout BEFORE INSERT ON generation_income_settlements FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_status_payout()');
        try {
            GenerationStatusCorrectionService::settle($admin, $allocation->id, $allocation->share_amount);
            $this->fail('Broken settlement accepted.');
        } catch (QueryException) {
            $this->assertSame('inactive', $root->fresh()->status);
            $this->assertSame($count, DB::table('financial_ledger')->count());
            $this->assertEquals($before, DB::table('generation_pool_accounts')->first());
            $this->assertDatabaseHas('generation_member_allocations', ['id' => $allocation->id, 'status' => 'held_unqualified']);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_status_payout ON generation_income_settlements');
        }
    }

    public function test_parallel_status_corrections_pay_once(): void
    {
        [$admin, $root, $allocation] = $this->held();
        $before = $root->fresh()->available_balance;
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/generation-status-correction-worker.php'), (string) $admin->id, (string) $allocation->id, $allocation->share_amount], base_path(), timeout: 30);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        }
        $this->assertSame(bcadd($before, $allocation->share_amount, 8), $root->fresh()->available_balance);
        $this->assertSame(1, DB::table('generation_income_settlements')->where('allocation_id', $allocation->id)->count());
        $this->assertSame(1, DB::table('member_security_audits')->where('action', 'generation_inactive_hold_corrected')->count());
    }

    public function test_disabled_payout_and_stake_created_after_allocation_cannot_authorize_release(): void
    {
        [$admin, $root, $allocation] = $this->held();
        $before = DB::table('financial_ledger')->count();
        config(['leobot.generation_income_settlement_enabled' => false]);
        try {
            GenerationStatusCorrectionService::settle($admin, $allocation->id, $allocation->share_amount);
            $this->fail('Disabled payout accepted.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Generation settlement disabled.', $error->getMessage());
        }
        config(['leobot.generation_income_settlement_enabled' => true]);
        DB::table('staking_positions')->where('user_id', $root->id)->update(['created_at' => now()->addDay()]);
        try {
            GenerationStatusCorrectionService::settle($admin, $allocation->id, $allocation->share_amount);
            $this->fail('Newer stake accepted for historical release.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('must predate', $error->getMessage());
        }
        $this->assertSame($before, DB::table('financial_ledger')->count());
        $this->assertSame('inactive', $root->fresh()->status);
    }
}
