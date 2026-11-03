<?php
namespace Tests\Feature;

use App\Services\StakingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StakingSyntheticFinancialTest extends TestCase
{
    private function ensureDisposable(): void
    {
        $identity = DB::selectOne('select current_database() as db, current_user as role');
        $this->assertSame('leobot_fullsuite_20261031_121634_525c1a23', $identity->db);
        $this->assertSame('lumiflowon_staking_test', $identity->role);
        $this->assertSame('testing', app()->environment());
    }

    private function syntheticUser(string $balance): int
    {
        $id = bin2hex(random_bytes(8));
        return DB::table('users')->insertGetId([
            'username' => 'synthetic_' . $id,
            'name' => 'Synthetic Staking Test',
            'email' => 'synthetic_' . $id . '@example.invalid',
            'phone' => '0000000000',
            'password' => 'test-disabled-login',
            'security_pin' => '000000',
            'status' => 'active',
            'role' => 'member',
            'is_blocked' => false,
            'available_balance' => $balance,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_synthetic_stake_debit_ledger_retry_and_rollback(): void
    {
        $this->ensureDisposable();
        DB::beginTransaction();
        try {
            $userId = $this->syntheticUser('100.00000000');
            $control = DB::table('staking_controls')->orderBy('id')->first();
            $this->assertNotNull($control);
            DB::table('staking_controls')->where('id', $control->id)->update(['enabled' => true]);
            $service = app(StakingService::class);
            $key = 'synthetic_' . bin2hex(random_bytes(12));
            $stakeId = $service->create($userId, '25.00000000', $key);
            $this->assertGreaterThan(0, $stakeId);
            $this->assertSame('75.00000000', number_format((float) DB::table('users')->where('id', $userId)->value('available_balance'), 8, '.', ''));
            $this->assertSame(1, DB::table('financial_ledger')->where('transaction_key', 'staking:' . $key)->count());
            $this->assertSame($stakeId, $service->create($userId, '25.00000000', $key));
            $this->assertSame(1, DB::table('financial_ledger')->where('transaction_key', 'staking:' . $key)->count());
            try {
                $service->create($userId, '999.00000000', 'synthetic_' . bin2hex(random_bytes(12)));
                $this->fail('Insufficient funds must reject stake');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Insufficient', $e->getMessage());
            }
            $this->assertSame(1, DB::table('staking_positions')->where('user_id', $userId)->count());
            $this->assertSame('75.00000000', number_format((float) DB::table('users')->where('id', $userId)->value('available_balance'), 8, '.', ''));
        } finally {
            DB::rollBack();
        }
    }
}