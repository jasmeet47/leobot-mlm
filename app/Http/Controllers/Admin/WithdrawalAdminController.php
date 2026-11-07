<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalMinimumPolicy;
use App\Services\WithdrawalPaymentConfirmationService;
use App\Services\WithdrawalRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

final class WithdrawalAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.withdrawals', [
            'settings' => DB::table('withdrawal_settings')->where('id', 1)->first(),
            'withdrawals' => DB::table('withdrawal_requests')->orderByDesc('id')->paginate(30),
            'writeEnabled' => config('leobot.withdrawals_enabled', false) === true &&
                (! app()->environment('production') || config('leobot.production_financial_approval', false) === true),
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        try {
            WithdrawalRequestService::approve($request->user(), $id);
        } catch (RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', 'Withdrawal approved for EXTERNAL payment; no transfer has been made.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        try {
            WithdrawalRequestService::reject($request->user(), $id);
        } catch (RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', 'Withdrawal rejected and reserved earnings refunded.');
    }

    public function updateMinimum(Request $request): RedirectResponse
    {
        $d = $request->validate(['minimum_usdt' => 'required|string|max:25']);
        try {
            WithdrawalMinimumPolicy::update($request->user(), $d['minimum_usdt']);
        } catch (RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['minimum' => $e->getMessage()]);
        }

        return back()->with('status', 'Minimum withdrawal saved successfully.');
    }

    public function updateFee(Request $request): RedirectResponse
    {
        $data = $request->validate(['principal_fee' => ['required', 'regex:/\A\d+(?:\.\d{1,9})?\z/D']]);
        try {
            WithdrawalRequestService::setPrincipalFeePercent($request->user(), $data['principal_fee']);
        } catch (RuntimeException|\InvalidArgumentException|AuthorizationException $e) {
            return back()->withErrors(['fee' => $e->getMessage()]);
        }

        return back()->with('status', 'Principal fee updated with audit. Existing requests retain their original fee.');
    }

    public function updateControls(Request $request): RedirectResponse
    {
        $data = $request->validate(['member_requests' => ['required', 'boolean'], 'admin_review' => ['required', 'boolean'], 'principal_release' => ['required', 'boolean']]);
        try {
            WithdrawalRequestService::setControls($request->user(), (bool) $data['member_requests'], (bool) $data['admin_review'], (bool) $data['principal_release']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['policy' => $e->getMessage()]);
        }

        return back()->with('status', 'Withdrawal controls updated with audit.');
    }

    public function payment(Request $request, int $id, WithdrawalPaymentConfirmationService $service): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:begin,submit,confirm'],
            'tx_hash' => ['required_unless:action,begin', 'nullable', 'string', 'max:66'],
            'external_payment_acknowledged' => ['accepted']]);
        $withdrawal = DB::table('withdrawal_requests')->where('id', $id)->first();
        abort_unless($withdrawal, 404);
        try {
            match ($data['action']) {
                'begin' => $service->beginPayment($request->user(), $id),
                'submit' => $service->submitPayment($request->user(), $id, $withdrawal->payment_network, $data['tx_hash']),
                'confirm' => $service->confirm($request->user(), $id, $withdrawal->payment_network, $data['tx_hash']),
            };
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', $data['action'] === 'confirm' ? 'On-chain payment verified and recorded.' : 'Payment state recorded; this panel has not sent a transfer.');
    }
}
