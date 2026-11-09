<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingProfitService;
use App\Services\StakingService;
use App\Services\WalletService;
use App\Support\DisposableDatabase;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class StakingConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        DisposableDatabase::assertSafe();
        FinancialSeedState::captureIfNeeded();
    }

    protected function afterTruncatingDatabase(): void
    {
        FinancialSeedState::restoreOrCapture();
    }

    /** @return list<array{ok: bool, id?: int, error?: string}> */
    private function simultaneous(array $requests, bool $disable = false, string $lockTable = 'staking_controls', int $lockId = 1): array
    {
        $processes = [];
        DB::beginTransaction();
        DB::table($lockTable)->where('id', $lockId)->lockForUpdate()->first();
        try {
            foreach ($requests as $request) {
                [$id, $amount, $key] = $request;
                $process = new Process([PHP_BINARY, base_path('tests/Support/staking-worker.php'), (string) $id, $amount, $key, ...array_map('strval', array_slice($request, 3))], base_path(), timeout: 20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            do {
                $ready = count(array_filter($processes, fn ($p) => str_contains($p->getOutput(), "ready\n")));
                if ($ready === count($requests)) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(count($requests), $ready, 'Every independent worker must reach the locked operation');
            if ($disable) {
                DB::table('staking_controls')->where('id', 1)->update(['enabled' => false]);
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    private function member(): User
    {
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);

        return User::factory()->create(['role' => 'member', 'status' => 'active', 'available_balance' => '100.00000000']);
    }

    public function test_simultaneous_duplicate_stakes_have_one_debit(): void
    {
        $member = $this->member();
        $request = [$member->id, '75', 'concurrent_stake_0001'];
        $results = $this->simultaneous([$request, $request]);
        $this->assertTrue($results[0]['ok']);
        $this->assertSame($results[0], $results[1]);
        $this->assertSame('25.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseCount('staking_positions', 1);
        $this->assertDatabaseCount('financial_ledger', 2);
    }

    public function test_different_concurrent_requests_cannot_overdraw_wallet(): void
    {
        $member = $this->member();
        $results = $this->simultaneous([[$member->id, '75', 'concurrent_stake_0001'], [$member->id, '75', 'concurrent_stake_0002']]);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertSame('25.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseCount('staking_positions', 1);
        $this->assertDatabaseCount('financial_ledger', 2);
    }

    public function test_conflicting_key_across_members_cannot_debit_both(): void
    {
        $a = $this->member();
        $b = $this->member();
        $results = $this->simultaneous([[$a->id, '75', 'concurrent_stake_0001'], [$b->id, '75', 'concurrent_stake_0001']]);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertSame('125.00000000', bcadd($a->fresh()->available_balance, $b->fresh()->available_balance, 8));
        $this->assertDatabaseCount('staking_positions', 1);
        $this->assertDatabaseCount('financial_ledger', 2);
    }

    public function test_off_switch_commit_rejects_waiting_stakes(): void
    {
        $member = $this->member();
        $results = $this->simultaneous([[$member->id, '75', 'concurrent_stake_0001'], [$member->id, '25', 'concurrent_stake_0002']], true);
        foreach ($results as $result) {
            $this->assertFalse($result['ok']);
            $this->assertSame('Staking disabled', $result['error']);
        }
        $this->assertSame('100.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseCount('staking_positions', 0);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_simultaneous_principal_requests_cannot_over_reserve_a_position(): void
    {
        config(['leobot.staking_enabled' => true, 'leobot.production_financial_approval' => true]);
        $member = $this->member();
        $position = (new StakingService)->create($member->id, '100', 'concurrent_seed_stake');
        DB::table('withdrawal_settings')->where('id', 1)->update(['member_requests_enabled' => true,
            'principal_release_enabled' => true, 'principal_withdrawal_fee_percent' => '1', 'principal_fee_configured_at' => now()]);
        $wallet = DB::table('usdt_member_wallets')->insertGetId(['user_id' => $member->id, 'network' => 'BEP20',
            'address' => '0x'.str_repeat('1', 40), 'verified_at' => now(), 'verification_method' => 'test_fixture',
            'proof_digest' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        $results = $this->simultaneous([[$member->id, '75', 'principal_concurrent_01', 'principal', $position, $wallet],
            [$member->id, '75', 'principal_concurrent_02', 'principal', $position, $wallet]], false, 'withdrawal_settings');
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertDatabaseCount('withdrawal_requests', 1);
        $this->assertDatabaseHas('staking_positions', ['id' => $position, 'withdrawn_amount' => '75.00000000']);
        $this->assertSame('0.00000000', $member->fresh()->available_balance);
    }

    public function test_simultaneous_profit_approvals_credit_members_once(): void
    {
        config(['leobot.staking_enabled' => true, 'leobot.production_financial_approval' => true, 'leobot.staking_profit_enabled' => true]);
        $member = $this->member();
        (new StakingService)->create($member->id, '100', 'concurrent_seed_stake');
        $admins = [];
        for ($i = 0; $i < 3; $i++) {
            $admins[] = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        }
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        $id = StakingProfitService::submit($admins[0], '9', 'TRADING-CONCURRENT-001', [$member->id => '9']);
        $results = $this->simultaneous([[$admins[1]->id, (string) $id, 'profit_verify_01', 'profit'],
            [$admins[2]->id, (string) $id, 'profit_verify_02', 'profit']], false, 'staking_profit_statements', $id);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame('0.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseCount('profit_sharing_fundings', 1);
        $this->assertDatabaseHas('profit_sharing_fundings', ['self_amount' => '6.03000000', 'generation_amount' => '2.70000000', 'magic_amount' => '0.27000000']);
        $this->assertSame(2, DB::table('financial_ledger')->where('reference_type', 'staking_profit_statement')->where('reference_id', $id)->count());
    }

    public function test_simultaneous_magic_enrollment_recovery_creates_one_position_without_a_second_debit(): void
    {
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot_magic.magic_matrix_enrollment_enabled' => false]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => false, 'minimum_tier_usdt' => '50']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        WalletService::credit($member->id, 'available_balance', '100', 'test_fixture', 'magic_concurrent_funding');
        app(StakingService::class)->create($member->id, '50', 'magic_concurrent_stake_001');
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true]);
        $ledger = DB::table('financial_ledger')->count();
        $request = [$member->id, '50', 'magic_recovery_00001', 'magic-enrollment'];
        $results = $this->simultaneous([$request, $request], false, 'magic_matrix_settings');
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('magic_matrix_positions', 1);
        $this->assertDatabaseCount('magic_matrix_cycles', 1);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->assertSame('50.00000000', $member->fresh()->available_balance);
    }
}
