<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminFundingWalletService as Funding;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

final class AdminFundingWalletController extends Controller
{
    public function index(): View
    {
        return view('admin.funding-wallet', ['balance' => Funding::balance(), 'history' => DB::table('financial_ledger as l')->leftJoin('users as u', 'u.id', '=', 'l.reference_user_id')->leftJoin('member_security_audits as a', 'a.id', '=', 'l.reference_id')->whereNull('l.user_id')->where('l.wallet_type', Funding::WALLET)->orderByDesc('l.id')->select('l.id', 'l.entry_type', 'l.amount', 'l.balance_after', 'l.created_at', 'u.username')->selectRaw("COALESCE(a.details->>'source_reference', a.details->>'request_key') as source_reference")->paginate(20)]);
    }

    public function topUp(Request $request): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'string', 'regex:/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', 'max:25'], 'source_reference' => 'required|string|min:3|max:120', 'funds_confirmed' => 'accepted']);
        try {
            Funding::topUp($request->user(), $data['amount'], $data['source_reference'], $request->boolean('funds_confirmed'));
        } catch (QueryException $error) {
            report($error);

            return back()->withErrors(['funding' => 'Funding could not be saved. No partial credit was kept.']);
        } catch (RuntimeException $error) {
            return back()->withErrors(['funding' => $error->getMessage()]);
        }

        return redirect()->route('admin.funding-wallet')->with('status', 'Company funding recorded successfully. Reusing the same receipt does not add it twice.');
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate(['member_id' => 'required|integer|min:1', 'funded_amount' => ['required', 'string', 'max:25', 'regex:/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D'], 'funded_activation_key' => 'required|uuid']);
        try {
            $position = Funding::activate($request->user(), (int) $data['member_id'], $data['funded_amount'], $data['funded_activation_key']);
        } catch (QueryException $error) {
            report($error);

            return back()->withErrors(['funding' => 'Funded activation could not be saved. The wallet debit was rolled back.']);
        } catch (RuntimeException $error) {
            return back()->withErrors(['funding' => $error->getMessage()]);
        }

        return back()->with('status', 'Member funded and activated successfully. '.$data['funded_amount'].' USDT deducted from Admin Funding Wallet. Staking #'.$position.'.');
    }
}
