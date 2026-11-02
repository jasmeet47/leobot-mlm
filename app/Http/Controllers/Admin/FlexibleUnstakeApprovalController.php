<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\FlexibleStakingService;
use Illuminate\Http\Request;
use Throwable;
final class FlexibleUnstakeApprovalController extends Controller {
    public function approve(Request $request, int $id, FlexibleStakingService $service) {
        try { $service->approve($request->user(),$id); return response()->json(['status'=>'approved']); }
        catch (Throwable $e) { if ($e instanceof \Illuminate\Database\QueryException) throw $e; return response()->json(['error'=>'Approval rejected'],409); }
    }
    public function reject(Request $request, int $id, FlexibleStakingService $service) {
        try { $service->reject($request->user(),$id); return response()->json(['status'=>'rejected']); }
        catch (Throwable $e) { if ($e instanceof \Illuminate\Database\QueryException) throw $e; return response()->json(['error'=>'Rejection failed'],409); }
    }
}
