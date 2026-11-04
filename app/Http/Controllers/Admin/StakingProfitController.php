<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StakingProfitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class StakingProfitController extends Controller
{
    public function index(): View
    {
        return view('admin.staking-profit', ['statements' => DB::table('staking_profit_statements')->orderByDesc('id')->paginate(20)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'string', 'max:30'],
            'evidence_reference' => ['required', 'string', 'max:150'], 'allocations' => ['required', 'string', 'max:50000'],
            'realized_profit_confirmed' => ['accepted']]);
        $allocations = [];
        foreach (preg_split('/\R/', trim($data['allocations'])) as $line) {
            if (! preg_match('/\A([1-9]\d*):\s*(\d+(?:\.\d{1,8})?)\z/D', trim($line), $match) || isset($allocations[(int) $match[1]])) {
                throw ValidationException::withMessages(['allocations' => 'Use one unique member ID: amount per line.']);
            }
            $allocations[(int) $match[1]] = $match[2];
        }
        try {
            StakingProfitService::submit($request->user(), $data['amount'], $data['evidence_reference'], $allocations);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['profit' => $e->getMessage()]);
        }

        return back()->with('status', 'Statement recorded; a different administrator must verify the realized profit and received funds.');
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $request->validate(['verified_funds_received' => ['accepted']]);
        try {
            StakingProfitService::approve($request->user(), $id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['profit' => $e->getMessage()]);
        }

        return back()->with('status', 'Verified variable profit credited. No future return is promised.');
    }
}
