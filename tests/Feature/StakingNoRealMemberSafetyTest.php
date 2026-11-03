<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Services\StakingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StakingNoRealMemberSafetyTest extends TestCase
{
    public function test_disposable_database_identity_and_staking_off_gate_without_members(): void
    {
        $identity = DB::selectOne('select current_database() as db, current_user as role');
        $this->assertSame('leobot_fullsuite_20261031_121634_525c1a23', $identity->db);
        $this->assertSame('lumiflowon_staking_test', $identity->role);
        $controls = DB::table('staking_controls')->orderBy('id')->get();
        $this->assertCount(1, $controls, 'Require exactly one staking control row');
        $this->assertFalse((bool) $controls[0]->enabled, 'Staking must be OFF');
        $before = DB::table('staking_positions')->count();
        try {
            app(StakingService::class)->create(2147483647, '1.00000000', 'safe_no_real_member_20261031');
            $this->fail('Staking OFF must reject stake creation');
        } catch (RuntimeException $e) {
            $this->assertSame('Staking disabled', $e->getMessage());
        }
        $this->assertSame($before, DB::table('staking_positions')->count());
    }
}