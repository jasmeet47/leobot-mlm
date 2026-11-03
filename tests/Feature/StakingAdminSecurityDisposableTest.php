<?php
namespace Tests\Feature;
use App\Services\StakingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
final class StakingAdminSecurityDisposableTest extends TestCase {
 private function synthetic(string $role): \App\Models\User {
  $tag=bin2hex(random_bytes(8));
  $id=DB::table('users')->insertGetId([
   'username'=>'security_'.$tag,'name'=>'Security Test',
   'email'=>'security_'.$tag.'@example.invalid','phone'=>'9'.(string)random_int(100000000,999999999),
   'password'=>'test-disabled-login','security_pin'=>'000000',
   'status'=>'active','role'=>$role,'is_blocked'=>false,
   'available_balance'=>'0','created_at'=>now(),'updated_at'=>now()
  ]);
  return \App\Models\User::findOrFail($id);
 }
 public function test_admin_toggle_audit_and_member_denial(): void {
  $identity=DB::selectOne('select current_database() as db, current_user as role');
  $this->assertSame('leobot_fullsuite_20261031_121634_525c1a23',$identity->db);
  $this->assertSame('lumiflowon_staking_test',$identity->role);
  $this->assertSame('testing',app()->environment());
  DB::beginTransaction();
  try {
   $control=DB::table('staking_controls')->orderBy('id')->first();
   $this->assertNotNull($control);
   $this->assertFalse((bool)$control->enabled);
   $admin=$this->synthetic('admin');
   $member=$this->synthetic('member');
   $service=app(StakingService::class);
   $count=DB::table('staking_control_audits')->count();
   try { $service->toggle($member,true); $this->fail('Member toggled staking'); }
   catch (\RuntimeException $e) { $this->assertSame('Forbidden',$e->getMessage()); }
   $this->assertSame($count,DB::table('staking_control_audits')->count());
   $service->toggle($admin,true);
   $this->assertTrue((bool)DB::table('staking_controls')->where('id',$control->id)->value('enabled'));
   $this->assertSame($count+1,DB::table('staking_control_audits')->count());
   $audit=DB::table('staking_control_audits')->orderByDesc('id')->first();
   $this->assertSame((int)$admin->id,(int)$audit->admin_id);
   $this->assertFalse((bool)$audit->old_enabled);
   $this->assertTrue((bool)$audit->new_enabled);
   $service->toggle($admin,false);
   $this->assertFalse((bool)DB::table('staking_controls')->where('id',$control->id)->value('enabled'));
   $this->assertSame($count+2,DB::table('staking_control_audits')->count());
  } finally { DB::rollBack(); }
 }
}
