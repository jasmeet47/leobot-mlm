<?php
namespace App\Http\Controllers\Member;
use App\Http\Controllers\Controller;
use App\Services\FlexibleStakingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;
final class FlexibleUnstakeController extends Controller {
    public function store(Request $request, FlexibleStakingService $service) {
        $data = $request->validate([
            'position_id'=>['required','integer','min:1'],
            'amount'=>['required','regex:/^\d+(\.\d{1,8})?$/'],
            'request_key'=>['required','regex:/^[A-Za-z0-9_-]{16,100}$/'],
            'security_pin'=>['required','string'],
        ]);
        if (!Hash::check($data['security_pin'], (string)$request->user()->security_pin)) {
            return response()->json(['error'=>'Invalid security PIN'],403);
        }
        try {
            $id = $service->request((int)$request->user()->id,(int)$data['position_id'],$data['amount'],$data['request_key']);
            return response()->json(['unstake_request_id'=>$id,'status'=>'pending'],201);
        } catch (Throwable $e) {
            if ($e instanceof \Illuminate\Database\QueryException) throw $e;
            return response()->json(['error'=>'Unstake rejected'],409);
        }
    }
}
