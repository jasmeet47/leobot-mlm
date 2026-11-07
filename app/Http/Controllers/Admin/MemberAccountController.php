<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivationService;
use App\Services\MemberAccountAdministration as Accounts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        return view('admin.member-account', ['member' => $member, 'lookupError' => $lookupError, 'mode' => $request->routeIs('admin.member-email') ? 'email' : 'status']);
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

    public function email(Request $r): RedirectResponse
    {
        $d = $r->validate(['member_id' => 'required|integer|min:1', 'old_email' => 'required|email|max:255', 'new_email' => 'required|email|max:255|confirmed']);
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
