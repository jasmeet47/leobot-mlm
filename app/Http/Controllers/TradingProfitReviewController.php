<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TradingProfitReviewerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

final class TradingProfitReviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(TradingProfitReviewerService::canReview($request->user()->id), 403);
        $statements = DB::table('staking_profit_statements')->whereIn('status', ['pending_verification', 'independently_verified'])
            ->where('submitted_by', '<>', $request->user()->id)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('staking_profit_allocations as a')
                ->whereColumn('a.statement_id', 'staking_profit_statements.id')->where('a.user_id', $request->user()->id))
            ->orderByDesc('id')->paginate(20);

        $allocationRows = DB::table('staking_profit_allocations')->whereIn('statement_id', $statements->pluck('id'))->orderBy('user_id')->get()->groupBy('statement_id');
        $digests = $statements->mapWithKeys(fn ($statement) => [$statement->id => TradingProfitReviewerService::evidenceDigest($statement, $allocationRows->get($statement->id, collect()))]);

        return view('reviewer.trading-profit', ['statements' => $statements,
            'allocations' => $allocationRows, 'digests' => $digests]);
    }

    public function attest(Request $request, int $id): RedirectResponse
    {
        abort_unless(TradingProfitReviewerService::canReview($request->user()->id), 403);
        $data = $request->validate(['verified_statement_and_received_funds' => ['accepted'], 'evidence_digest' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D']]);
        try {
            TradingProfitReviewerService::attest($request->user(), $id, $data['evidence_digest']);
        } catch (RuntimeException $error) {
            return back()->withErrors(['review' => $error->getMessage()]);
        }

        return back()->with('status', 'Independent approval recorded. No wallet credit or payout has been made.');
    }

    public function permission(Request $request): RedirectResponse
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:100'], 'enabled' => ['required', 'boolean'], 'trusted_independent_person' => ['accepted']]);
        $member = User::query()->select(['id'])->where('username', trim($data['username']))->first();
        if (! $member) {
            return back()->withErrors(['reviewer' => 'Existing member username not found.']);
        }
        try {
            TradingProfitReviewerService::setEnabled($request->user(), $member->id, (bool) $data['enabled']);
        } catch (RuntimeException $error) {
            return back()->withErrors(['reviewer' => $error->getMessage()]);
        }

        return back()->with('status', 'Reviewer permission saved with an audit entry. Member role and financial balances are unchanged.');
    }
}
