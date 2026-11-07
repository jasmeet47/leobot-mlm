<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\MemberPaymentSecurity;
use App\Services\WithdrawalEmailOtpService;
use App\Services\WithdrawalRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

final class WithdrawalMemberController extends Controller
{
    public function index(Request $request): View
    {
        return view('member.withdrawals', [
            'history' => DB::table('withdrawal_requests')->where('user_id', $request->user()->id)
                ->orderByDesc('id')->paginate(20),
            'verifiedWallets' => MemberPaymentSecurity::wallets($request->user()->id),
            'enabled' => config('leobot.withdrawals_enabled', false) === true &&
                (! app()->environment('production') || config('leobot.production_financial_approval', false) === true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'regex:/\A\d+(?:\.\d{1,8})?\z/D'],
            'request_key' => ['required', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._:-]{2,99}\z/D'],
            'usdt_wallet_id' => ['required', 'integer', 'min:1'],
            'withdrawal_otp' => ['required', 'string', 'size:6'], 'otp_challenge_id' => ['required', 'integer', 'min:1'],
        ]);
        try {
            WithdrawalEmailOtpService::execute($request->user(), $data, (int) $data['otp_challenge_id'], $data['withdrawal_otp'], fn () => WithdrawalRequestService::request($request->user(), $data['amount'], $data['request_key'], 'earnings', (int) $data['usdt_wallet_id']));
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['withdrawal' => 'Withdrawal could not be saved. No funds were transferred.']);
        } catch (RuntimeException|\InvalidArgumentException|AuthorizationException $e) {
            return back()->withInput($request->except('withdrawal_otp'))->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', 'Email OTP verified successfully. Withdrawal requested; amount held pending review.');
    }
}
