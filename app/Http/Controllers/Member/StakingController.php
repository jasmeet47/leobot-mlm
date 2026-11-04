<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\StakingPrincipalService;
use App\Services\StakingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

final class StakingController extends Controller
{
    public function index(Request $request): View
    {
        return view('member.staking', [
            'member' => $request->user(),
            'positions' => DB::table('staking_positions')->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(20),
            'wallets' => DB::table('usdt_member_wallets')->where('user_id', $request->user()->id)->whereNotNull('verified_at')->whereNull('revoked_at')->get(),
            'policy' => DB::table('withdrawal_settings')->where('id', 1)->first(),
            'stakingEnabled' => config('leobot.staking_enabled', false) === true && config('leobot.production_financial_approval', false) === true
                && (bool) DB::table('staking_controls')->orderBy('id')->value('enabled'),
        ]);
    }

    public function withdraw(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'string', 'max:30'],
            'funding_source' => ['sometimes', 'in:deposit,rewards'], 'request_key' => ['required', 'string', 'max:100'], 'usdt_wallet_id' => ['required', 'integer', 'min:1'],
            'expected_fee_percent' => ['required', 'string', 'regex:/\A\d+(?:\.\d{1,9})?\z/D']]);
        try {
            StakingPrincipalService::request($request->user(), $id, $data['amount'], $data['request_key'], (int) $data['usdt_wallet_id'], $data['expected_fee_percent']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['staking' => $e->getMessage()]);
        }

        return redirect()->route('member.withdrawals.index')->with('status', 'Principal reserved for external payout. Your request records its fee, network and net amount.');
    }

    public function store(Request $request, StakingService $service)
    {
        $data = $request->validate(['amount' => ['required', 'string', 'max:100', 'regex:/^\d+(\.\d{1,8})?$/'], 'request_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]{16,100}$/']]);
        try {
            $id = $service->create((int) $request->user()->id, $data['amount'], $data['request_key'], ($data['funding_source'] ?? 'deposit') === 'deposit' ? 'activation_balance' : 'available_balance');

            return $request->expectsJson() ? response()->json(['stake_id' => $id], 201) : back()->with('status', 'Stake created. No fixed return or lock period applies.');
        } catch (Throwable $e) {
            if ($e instanceof QueryException) {
                throw $e;
            }

            return $request->expectsJson() ? response()->json(['error' => 'Stake rejected'], 409) : back()->withErrors(['staking' => 'Stake rejected. Check available balance and administrator controls.']);
        }
    }
}
