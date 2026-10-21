<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\VerifiedDepositService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
final class DepositReviewController extends Controller {
 public function index(){ $deposits=DB::table('member_deposit_requests')->orderByDesc('id')->limit(100)->get(); return view('admin.deposit-review',compact('deposits')); }
 public function approve(Request $request,int $id){
  $data=$request->validate(['verification_reference'=>'required|regex:/\A[A-Za-z0-9._:-]{8,150}\z/D']);
  try{VerifiedDepositService::approve((int)$request->user()->id,$id,$data['verification_reference']);}
  catch(Throwable $e){return back()->withErrors(['approval'=>$e->getMessage()]);}
  return back()->with('status','Deposit approved and credited.');
 }
 public function reject(Request $request,int $id){
  try{VerifiedDepositService::reject((int)$request->user()->id,$id);}
  catch(Throwable $e){return back()->withErrors(['rejection'=>$e->getMessage()]);}
  return back()->with('status','Deposit rejected without wallet credit.');
 }
}
