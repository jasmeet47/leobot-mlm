<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
final class StakingService {
 public function create(int $userId,string $amount,string $key): int {
  if (!preg_match('/^\d+(\.\d{1,8})?$/D',$amount) || bccomp($amount,'0',8)<=0 || bccomp($amount,'999999999999.99999999',8)>0) throw new RuntimeException('Invalid amount');
  if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/D',$key)) throw new RuntimeException('Invalid request key');
  return DB::transaction(function()use($userId,$amount,$key){
   $control=DB::table('staking_controls')->orderBy('id')->lockForUpdate()->first();
   if (!$control || !$control->enabled) throw new RuntimeException('Staking disabled');
   $user=User::whereKey($userId)->lockForUpdate()->firstOrFail();
   if ($user->is_blocked || $user->status!=='active' || $user->role!=='member') throw new RuntimeException('Member not eligible');
   $existing=DB::table('staking_positions')->where('request_key',$key)->first();
   if ($existing) {
    if ((int)$existing->user_id !== $userId || bccomp((string)$existing->amount,$amount,8)!==0) throw new RuntimeException('Request key conflict');
    return (int)$existing->id;
   }
   $id=DB::table('staking_positions')->insertGetId(['user_id'=>$userId,'amount'=>$amount,'request_key'=>$key,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
   WalletService::debit($userId,'available_balance',$amount,'staking_principal','staking:'.$key,['reference_type'=>'staking_position','reference_id'=>$id]);
   return $id;
  },3);
 }
 public function toggle(User $admin,bool $enabled): void {
  if ($admin->is_blocked || $admin->status!=='active' || !in_array($admin->role,['admin','super_admin'],true)) throw new RuntimeException('Forbidden');
  DB::transaction(function()use($admin,$enabled){
   $row=DB::table('staking_controls')->orderBy('id')->lockForUpdate()->first();
   if (!$row) {DB::table('staking_controls')->insert(['enabled'=>false,'created_at'=>now(),'updated_at'=>now()]);$row=DB::table('staking_controls')->orderBy('id')->lockForUpdate()->first();}
   DB::table('staking_controls')->where('id',$row->id)->update(['enabled'=>$enabled,'updated_at'=>now()]);
   DB::table('staking_control_audits')->insert(['admin_id'=>$admin->id,'old_enabled'=>$row->enabled,'new_enabled'=>$enabled,'created_at'=>now(),'updated_at'=>now()]);
  },3);
 }
}
