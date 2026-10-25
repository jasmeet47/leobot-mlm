<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
final class DemoSimulationService {
 public static function guard(): void {
  $url=(string)config('app.url');
  $expected=(string)env('DEMO_SIMULATION_DB_HOST','');
  $dbUrl=(string)config('database.connections.pgsql.url');
  $host=$dbUrl ? (string)parse_url($dbUrl,PHP_URL_HOST) : (string)config('database.connections.pgsql.host');
  if (!filter_var(env('DEMO_SIMULATION_ENABLED',false),FILTER_VALIDATE_BOOLEAN)
      || !in_array($url,['https://leobot-demo.onrender.com','http://127.0.0.1:8000'],true)
      || !$expected || $host!==$expected || DB::getDriverName()!=='pgsql'
      || !str_starts_with($host,'ep-shy-thunder-b4edgbov.') && $host!=='127.0.0.1') {
   abort(403,'Demo simulation disabled or database identity mismatch.');
  }
 }
 public static function balance(int $userId): string {
  return (string)(DB::table('demo_sim_wallets')->where('user_id',$userId)->value('balance')??'0.00000000');
 }
 public static function transfer(int $userId,string $kind,string $amount,string $ref): string {
  self::guard();
  if(!in_array($kind,['credit','debit'],true)||!preg_match('/^(?:0|[1-9]\d{0,10})(?:\.\d{1,8})?$/',$amount)||bccomp($amount,'0',8)<=0||bccomp($amount,'1000',8)>0)throw new RuntimeException('Invalid simulated amount.');
  if(!preg_match('/^[a-zA-Z0-9_-]{12,100}$/',$ref))throw new RuntimeException('Invalid reference.');
  return DB::transaction(function()use($userId,$kind,$amount,$ref){
   $old=DB::table('demo_sim_ledger')->where('reference',$ref)->first();
   if($old){if((int)$old->user_id!==$userId||$old->kind!==$kind||bccomp((string)$old->amount,$amount,8)!==0)throw new RuntimeException('Reference collision.');return (string)$old->balance_after;}
   DB::table('demo_sim_wallets')->insertOrIgnore(['user_id'=>$userId,'balance'=>'0.00000000','created_at'=>now(),'updated_at'=>now()]);
   $wallet=DB::table('demo_sim_wallets')->where('user_id',$userId)->lockForUpdate()->first();
   // Recheck after wallet lock for concurrent idempotent requests.
   $old=DB::table('demo_sim_ledger')->where('reference',$ref)->first();
   if($old){if((int)$old->user_id!==$userId||$old->kind!==$kind||bccomp((string)$old->amount,$amount,8)!==0)throw new RuntimeException('Reference collision.');return (string)$old->balance_after;}
   $next=$kind==='credit'?bcadd((string)$wallet->balance,$amount,8):bcsub((string)$wallet->balance,$amount,8);
   if(bccomp($next,'0',8)<0)throw new RuntimeException('Insufficient simulated balance.');
   DB::table('demo_sim_wallets')->where('user_id',$userId)->update(['balance'=>$next,'updated_at'=>now()]);
   DB::table('demo_sim_ledger')->insert(['user_id'=>$userId,'reference'=>$ref,'kind'=>$kind,'amount'=>$amount,'balance_after'=>$next,'created_at'=>now(),'updated_at'=>now()]);
   return $next;
  },3);
 }
}
