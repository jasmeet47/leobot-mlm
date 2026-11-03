<?php
namespace App\Http\Controllers\Member;
use App\Http\Controllers\Controller;
use App\Services\StakingService;
use Illuminate\Http\Request;
use Throwable;
final class StakingController extends Controller {
 public function store(Request $request,StakingService $service) {
  $data=$request->validate(['amount'=>['required','regex:/^\d+(\.\d{1,8})?$/'],'request_key'=>['required','regex:/^[A-Za-z0-9_-]{16,100}$/']]);
  try {$id=$service->create((int)$request->user()->id,$data['amount'],$data['request_key']);return response()->json(['stake_id'=>$id],201);}
  catch(Throwable $e){if($e instanceof \Illuminate\Database\QueryException)throw $e;return response()->json(['error'=>'Stake rejected'],409);}
 }
}
