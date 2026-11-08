<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\IncomeDistributionService as Distribution;
use App\Services\StakingProfitService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class IncomeDistributionController extends Controller
{
    public function index(): View
    {
        return view('admin.income-distribution', ['requests' => DB::table('income_distribution_requests as r')->join('staking_profit_statements as s', 's.id', '=', 'r.statement_id')->select('r.*', 's.amount', 's.status', 's.evidence_reference', 's.submitted_by as statement_submitter')->orderByDesc('r.id')->paginate(25), 'weights' => Distribution::weights()]);
    }

    public function submit(Request $r): RedirectResponse
    {
        $d = $r->validate(['amount' => ['required', 'string', 'max:25'], 'submission_token' => 'required|uuid', 'period' => 'required|in:daily,weekly,monthly', 'period_date' => 'required|date_format:d/m/Y', 'period_end_date' => 'required|date_format:d/m/Y|after_or_equal:period_date', 'realized_profit_confirmed' => 'accepted']);
        try {
            Distribution::submit($r->user(), $d['amount'], 'AUTO-'.$d['submission_token'], $d['period'], CarbonImmutable::createFromFormat('!d/m/Y', $d['period_date'])->format('Y-m-d'), CarbonImmutable::createFromFormat('!d/m/Y', $d['period_end_date'])->format('Y-m-d'), $d['submission_token']);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['distribution' => 'Unable to save this income request.']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['distribution' => $e->getMessage()]);
        }

        return back()->with('status', 'Profit saved successfully. Verify it before distribution.');
    }

    public function reserve(Request $r, int $id): RedirectResponse
    {
        $r->validate(['verified_funds_received' => 'accepted']);
        abort_unless(DB::table('income_distribution_requests')->where('statement_id', $id)->exists(), 404);
        try {
            StakingProfitService::reserveReviewed($r->user(), $id);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['distribution' => 'Unable to reserve the verified income.']);
        } catch (\RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['distribution' => $e->getMessage()]);
        }

        return back()->with('status', 'Approved profit reserved 67% Self / 30% 51-Level / 3% Magic. Wallet payouts remain subject to payout controls and qualifications.');
    }

    public function verifyAndDistribute(Request $r, int $id): RedirectResponse
    {
        $r->validate(['verified_funds_received' => 'accepted']);
        abort_unless(DB::table('income_distribution_requests')->where('statement_id', $id)->exists(), 404);
        try {
            StakingProfitService::verifyByAdmin($r->user(), $id);
            Distribution::distribute($r->user(), $id);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['distribution' => 'Wallet payout failed. Verified funds remain reserved; retry distribution without creating another profit statement.']);
        } catch (\RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['distribution' => $e->getMessage().' No new payout committed; check the statement status before retrying.']);
        }

        return back()->with('status', 'Profit verified and qualified income credited successfully. Ineligible shares remain held.');
    }

    public function distribute(Request $r, int $id): RedirectResponse
    {
        $r->validate(['verified_funds_received' => 'accepted']);
        try {
            Distribution::distribute($r->user(), $id);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['distribution' => 'Payout transaction failed; no partial wallet credits were kept.']);
        } catch (\RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['distribution' => $e->getMessage()]);
        }

        return back()->with('status', 'Income distribution processed. Qualified shares credited; unqualified, capped or unallocated shares remain held in their reserves.');
    }
}
