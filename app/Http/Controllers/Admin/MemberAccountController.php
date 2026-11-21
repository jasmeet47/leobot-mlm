<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivationService;
use App\Services\AdminFundingWalletService;
use App\Services\MemberAccountAdministration as Accounts;
use App\Services\MemberWithdrawalControl;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MemberAccountController extends Controller
{
    public function index(Request $request): View
    {
        $d = $request->validate(['member' => 'nullable|string|max:255']);
        $member = null;
        $lookupError = null;
        if (filled($d['member'] ?? null)) {
            try {
                $member = Accounts::find($d['member']);
                if (! $member) {
                    $lookupError = 'No member found. Check the User ID or email.';
                }
            } catch (\RuntimeException $e) {
                $lookupError = $e->getMessage();
            }
        }

        $adminActivatedMembers = DB::table('users')->where('users.role', 'member')->whereNull('users.deleted_at')->whereExists(function ($query): void {
            $query->selectRaw('1')->from('member_security_audits as activation')->whereRaw("activation.details->>'user_id' = users.id::text")->where(function ($query): void {
                $query->whereIn('activation.action', ['admin_member_account_activation', 'admin_verified_activation'])->orWhere(function ($query): void {
                    $query->where('activation.action', 'admin_member_status')->whereRaw("activation.details->>'after' = ?", ['active']);
                });
            });
        })->select('users.id', 'users.username', 'users.name', 'users.status')
            ->selectSub(DB::table('member_security_audits as control')->where('control.action', 'admin_member_withdrawals')->whereRaw("control.details->>'user_id' = users.id::text")->orderByDesc('control.id')->limit(1)->selectRaw("control.details->>'enabled'"), 'withdrawals_enabled')->orderBy('users.username')->paginate(20)->withQueryString();

        return view('admin.member-account', ['adminFundingBalance' => AdminFundingWalletService::balance(), 'adminActivatedMembers' => $adminActivatedMembers, 'member' => $member, 'lookupError' => $lookupError, 'mode' => $request->routeIs('admin.member-email') ? 'email' : 'status']);
    }

    public function status(Request $r): RedirectResponse
    {
        $d = $r->validate(['member_id' => 'required|integer|min:1', 'status' => 'required|in:active,inactive', 'expected_status' => 'required|in:active,inactive', 'amount' => ['required_if:status,active', 'nullable', 'string', 'max:25', 'regex:/\A\d+(?:\.\d{1,8})?\z/D'], 'activation_key' => 'required_if:status,active|nullable|uuid']);
        try {
            if ($d['status'] === 'active') {
                $member = User::findOrFail($d['member_id']);
                $id = ActivationService::activateByAdmin($r->user(), $member, $d['amount'], $d['activation_key']);

                return back()->with('status', 'Member activated successfully. '.$d['amount'].' USDT debited from verified balance. Activation #'.$id.'.');
            }
            Accounts::status($r->user(), (int) $d['member_id'], $d['status'], $d['expected_status']);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['member' => 'Account update failed. No partial debit was kept.']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['member' => $e->getMessage()]);
        }

        return back()->with('status', 'Member set inactive successfully. Existing balances and financial history are preserved.');
    }

    public function activateAccount(Request $request): RedirectResponse
    {
        $data = $request->validate(['member_id' => 'required|integer|min:1', 'expected_status' => 'required|in:active,inactive']);
        try {
            Accounts::activateAccount($request->user(), (int) $data['member_id'], $data['expected_status']);
        } catch (QueryException $error) {
            report($error);

            return back()->withErrors(['member' => 'Account control could not be saved. Please try again.']);
        } catch (\RuntimeException $error) {
            return back()->withErrors(['member' => $error->getMessage()]);
        }

        return back()->with('status', 'Account activated successfully. No wallet debit or package was created.');
    }

    public function withdrawals(Request $request): RedirectResponse
    {
        $data = $request->validate(['member_id' => 'required|integer|min:1', 'enabled' => 'required|boolean', 'expected_enabled' => 'required|boolean']);
        try {
            MemberWithdrawalControl::set($request->user(), (int) $data['member_id'], $request->boolean('enabled'), $request->boolean('expected_enabled'));
        } catch (QueryException $error) {
            report($error);

            return back()->withErrors(['member' => 'Account control could not be saved. Please try again.']);
        } catch (\RuntimeException $error) {
            return back()->withErrors(['member' => $error->getMessage()]);
        }

        return back()->with('status', $request->boolean('enabled') ? 'Member withdrawals turned ON successfully.' : 'Member withdrawals turned OFF successfully.');
    }

    public function email(Request $r): RedirectResponse
    {
        $d = $r->validate(['member_id' => 'required|integer|min:1', 'old_email' => 'required|email|max:255', 'new_email' => 'required|email|max:255']);
        $d['new_email'] = trim(strtolower($d['new_email']));
        try {
            Accounts::email($r->user(), (int) $d['member_id'], $d['old_email'], $d['new_email']);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['email' => 'Email could not be changed. Check whether it is already in use.']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()->route('admin.member-email', ['member' => (string) $d['member_id']])->with('status', 'Email changed successfully. ईमेल सफलतापूर्वक बदल दिया गया है।');
    }
}
