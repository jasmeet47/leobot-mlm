<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StakingProfitService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class StakingProfitController extends Controller
{
    public function index(): View
    {
        return view('admin.staking-profit', ['statements' => DB::table('staking_profit_statements')->orderByDesc('id')->paginate(20),
            'reviewerGrants' => DB::table('trading_profit_reviewers as r')->join('users as u', 'u.id', '=', 'r.user_id')->get(['u.username', 'r.enabled'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['period' => $request->input('period', 'daily')]);

        return app(IncomeDistributionController::class)->submit($request);
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        try {
            $statement = DB::table('staking_profit_statements')->find($id);
            abort_unless($statement, 404);
            StakingProfitService::verifyByAdmin($request->user(), $id);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['profit' => 'Verification could not be saved.']);
        } catch (\RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['profit' => $e->getMessage()]);
        }

        return back()->with('status', 'Profit verified successfully. Approved shares are reserved for distribution.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        try {
            StakingProfitService::reject($request->user(), $id);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['profit' => 'Rejection could not be saved.']);
        } catch (\RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['profit' => $e->getMessage()]);
        }

        return back()->with('status', 'Profit statement rejected successfully.');
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $request->validate(['verified_funds_received' => ['accepted']]);
        try {
            StakingProfitService::approve($request->user(), $id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['profit' => $e->getMessage()]);
        }

        return back()->with('status', 'Verified profit reserved: 67% Self, 30% Generation, 3% Magic. Payouts require their separate controls.');
    }

    public function reserve(Request $request, int $id): RedirectResponse
    {
        $request->validate(['verified_funds_received' => ['accepted']]);
        try {
            StakingProfitService::reserveReviewed($request->user(), $id);
        } catch (\RuntimeException $error) {
            return back()->withErrors(['profit' => $error->getMessage()]);
        }

        return back()->with('status', 'Independently approved profit reserved 67/30/3. No member payout has been made.');
    }
}
