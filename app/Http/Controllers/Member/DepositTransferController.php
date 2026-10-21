<?php
namespace App\Http\Controllers\Member;
use App\Http\Controllers\Controller;
use App\Services\VerifiedDepositService;
use App\Services\MemberFundTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
final class DepositTransferController extends Controller {
 public function index(Request $request) {
  $deposits=DB::table('member_deposit_requests')->where('user_id',$request->user()->id)->orderByDesc('id')->limit(30)->get();
  return view('member.deposit-transfer',compact('deposits'));
 }
 public function deposit(Request $request) {
  $data=$request->validate(['amount'=>['required','regex:/\A\d+(?:\.\d{1,8})?\z/D'],'network'=>'required|in:TRC20,BEP20','tx_hash'=>'required|alpha_num|between:20,150','request_key'=>'required|regex:/\A[A-Za-z0-9_-]{8,100}\z/D']);
  try { VerifiedDepositService::submit((int)$request->user()->id,$data['amount'],$data['network'],$data['tx_hash'],$data['request_key']); }
  catch(Throwable $e){return back()->withErrors(['deposit'=>$e->getMessage()]);}
  return back()->with('status','Deposit evidence submitted for independent admin verification.');
 }
 public function transfer(Request $request) {
  $data=$request->validate(['recipient_id'=>'required|integer|min:1','amount'=>['required','regex:/\A\d+(?:\.\d{1,8})?\z/D'],'request_key'=>'required|regex:/\A[A-Za-z0-9_-]{8,100}\z/D']);
  try {MemberFundTransferService::transfer((int)$request->user()->id,(int)$data['recipient_id'],$data['amount'],$data['request_key']);}
  catch(Throwable $e){return back()->withErrors(['transfer'=>$e->getMessage()]);}
  return back()->with('status','Transfer completed.');
 }
}
