<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingService;
use App\Services\FlexibleStakingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use RuntimeException;

/** Runs ONLY against the verified local disposable database; rolls back every test. */
final class FlexibleStakingFinancialIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $cfg = config('database.connections.pgsql');
        if (config('database.default') !== 'pgsql' || ($cfg['host'] ?? null) !== '127.0.0.1' ||
            (string)($cfg['port'] ?? '') !== '5433' || ($cfg['database'] ?? null) !== 'leobot_financial_disposable' ||
            ($cfg['username'] ?? null) !== 'lumiflowon_staking_test') {
            throw new RuntimeException('REFUSED_NON_DISPOSABLE_DATABASE');
        }
        DB::beginTransaction();
    }
    protected function tearDown(): void
    {
        try { if (DB::transactionLevel() > 0) DB::rollBack(); }
        finally { parent::tearDown(); }
    }
    private function fixtures(): array
    {
        // Dedicated disposable-only identities, created inside the outer test transaction.
        // Never modify or borrow existing users' wallet balances.
        $member = User::factory()->create([
            'status' => 'active',
            'security_pin' => bcrypt('123456'),
        ]);
        $member->forceFill([
            'role' => 'member',
            'is_blocked' => false,
            'available_balance' => '500.00000000',
        ])->save();
        $admin = User::factory()->create([
            'status' => 'active',
            'security_pin' => bcrypt('654321'),
        ]);
        $admin->forceFill([
            'role' => 'admin',
            'is_blocked' => false,
        ])->save();
        DB::table('staking_controls')->update(['enabled' => true]);
        return [$member->fresh(), $admin->fresh()];
    }
    private function key(): string { return 'financial_test_' . bin2hex(random_bytes(12)); }
    public function test_debit_partial_full_approval_and_double_credit_prevention(): void
    {
        [$member,$admin] = $this->fixtures();
        $stake = app(StakingService::class)->create($member->id,'100.00000000',$this->key());
        $this->assertSame(0,bccomp((string)$member->fresh()->available_balance,'400',8));
        $this->assertSame(0,bccomp((string)DB::table('staking_positions')->where('id',$stake)->value('remaining_amount'),'100',8));
        $req = app(FlexibleStakingService::class)->request($member->id,$stake,'40',$this->key());
        $this->assertSame(0,bccomp((string)$member->fresh()->available_balance,'400',8));
        app(FlexibleStakingService::class)->approve($admin,$req);
        $this->assertSame(0,bccomp((string)$member->fresh()->available_balance,'440',8));
        $this->assertSame(0,bccomp((string)DB::table('staking_positions')->where('id',$stake)->value('remaining_amount'),'60',8));
        try { app(FlexibleStakingService::class)->approve($admin,$req); $this->fail('Duplicate approval succeeded'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('already processed',$e->getMessage()); }
        $this->assertSame(0,bccomp((string)$member->fresh()->available_balance,'440',8));
        $last = app(FlexibleStakingService::class)->request($member->id,$stake,'60',$this->key());
        app(FlexibleStakingService::class)->approve($admin,$last);
        $this->assertSame(0,bccomp((string)$member->fresh()->available_balance,'500',8));
        $this->assertSame('closed',DB::table('staking_positions')->where('id',$stake)->value('status'));
        $this->assertSame(2,DB::table('financial_ledger')->whereIn('transaction_key',['staking:unstake:'.$req,'staking:unstake:'.$last])->count());
    }
    public function test_pending_overcommit_rejected_and_rejection_returns_no_principal(): void
    {
        [$member,$admin] = $this->fixtures();
        $stake = app(StakingService::class)->create($member->id,'20',$this->key());
        $req = app(FlexibleStakingService::class)->request($member->id,$stake,'15',$this->key());
        try { app(FlexibleStakingService::class)->request($member->id,$stake,'6',$this->key()); $this->fail('Overcommit succeeded'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('exceeds',$e->getMessage()); }
        app(FlexibleStakingService::class)->reject($admin,$req);
        $this->assertSame(0,bccomp((string)$member->fresh()->available_balance,'480',8));
        $this->assertSame(0,DB::table('financial_ledger')->where('transaction_key','staking:unstake:'.$req)->count());
    }
    public function test_insufficient_wallet_debit_rolls_back_stake_and_ledger(): void
    {
        [$member] = $this->fixtures();
        $key = $this->key();
        try { app(StakingService::class)->create($member->id,'600',$key); $this->fail('Insufficient debit succeeded'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('Insufficient',$e->getMessage()); }
        $this->assertSame(0,DB::table('staking_positions')->where('request_key',$key)->count());
        $this->assertSame(0,DB::table('financial_ledger')->where('transaction_key','staking:'.$key)->count());
        $this->assertSame(0,bccomp((string)$member->fresh()->available_balance,'500',8));
    }
}
