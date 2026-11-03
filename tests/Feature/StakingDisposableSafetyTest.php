<?php
namespace Tests\Feature;

use App\Services\StakingService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('staking-disposable')]
final class StakingDisposableSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        $this->assertSame('5433', (string) config('database.connections.pgsql.port'));
        $this->assertSame('leobot_fullsuite_20261031_121634_525c1a23', config('database.connections.pgsql.database'));
        $this->assertSame('lumiflowon_staking_test', config('database.connections.pgsql.username'));
        $this->assertSame('leobot_fullsuite_20261031_121634_525c1a23', DB::selectOne('select current_database() as db')->db);
        $this->assertSame('lumiflowon_staking_test', DB::selectOne('select current_user as role')->role);
        foreach (['staking_controls', 'staking_positions', 'staking_control_audits', 'financial_ledger', 'users'] as $table) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable($table), "Missing table: $table");
        }
    }

    public function test_staking_is_disabled_when_control_is_off(): void
    {
        $control = DB::table('staking_controls')->orderBy('id')->first();
        $this->assertNotNull($control, 'Staking control row missing; do not seed automatically');
        $this->assertFalse((bool) $control->enabled, 'Staking must be OFF before these tests');
        $before = DB::table('staking_positions')->count();
        try {
            app(StakingService::class)->create(2147483647, '1.00000000', 'BetaSafetyOffOnly123456789');
            $this->fail('Disabled staking unexpectedly accepted a request');
        } catch (\RuntimeException $e) {
            $this->assertSame('Staking disabled', $e->getMessage());
        }
        $this->assertSame($before, DB::table('staking_positions')->count());
    }

    public function test_invalid_amount_and_request_key_are_rejected_without_writes(): void
    {
        $before = DB::table('staking_positions')->count();
        foreach ([['0', 'BetaSafetyInvalid1234567'], ['-1', 'BetaSafetyInvalid1234567'], ['1.000000001', 'BetaSafetyInvalid1234567'], ['1', 'short']] as [$amount, $key]) {
            try {
                app(StakingService::class)->create(2147483647, $amount, $key);
                $this->fail('Invalid input accepted');
            } catch (\RuntimeException $e) {
                $this->assertContains($e->getMessage(), ['Invalid amount', 'Invalid request key']);
            }
        }
        $this->assertSame($before, DB::table('staking_positions')->count());
    }
}