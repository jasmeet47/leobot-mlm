<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\VerifiedDepositService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DepositReviewController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', 'in:all,pending,approved,rejected']]);
        $query = DB::table('member_deposit_requests as d')->join('users as u', 'u.id', '=', 'd.user_id')->select('d.*', 'u.username', 'u.name as member_name', 'u.email as member_email');
        if ($q = $data['q'] ?? null) {
            $query->where(function ($x) use ($q): void {
                $x->where('u.username', 'ilike', '%'.$q.'%')->orWhere('u.name', 'ilike', '%'.$q.'%')->orWhere('u.email', 'ilike', '%'.$q.'%')->orWhere('d.tx_hash', 'ilike', '%'.$q.'%');
            });
        }
        $status = $data['status'] ?? 'pending';
        if ($status !== 'all') {
            $query->where('d.status', $status);
        }

        return view('admin.deposit-review', ['deposits' => $query->orderByDesc('d.id')->paginate(30)->withQueryString(), 'pendingCount' => DB::table('member_deposit_requests')->where('status', 'pending')->count()]);
    }

    public function notifications(): JsonResponse
    {
        return response()->json(['pending' => DB::table('member_deposit_requests')->where('status', 'pending')->count(), 'last_id' => (int) DB::table('member_deposit_requests')->where('status', 'pending')->max('id')])->header('Cache-Control', 'private, no-store');
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        try {
            VerifiedDepositService::approve((int) $request->user()->id, $id);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['approval' => 'Unable to save this approval. No partial credit was kept. Please refresh and try again.']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', 'Deposit approved and credited once after on-chain verification.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        try {
            VerifiedDepositService::reject((int) $request->user()->id, $id);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['rejection' => 'Unable to save this review. Please try again.']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['rejection' => $e->getMessage()]);
        }

        return back()->with('status', 'Deposit rejected without wallet credit.');
    }
}
