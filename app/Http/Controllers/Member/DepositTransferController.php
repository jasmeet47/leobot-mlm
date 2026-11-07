<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\DepositDestinationService;
use App\Services\MemberTransferPolicy;
use App\Services\VerifiedDepositService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DepositTransferController extends Controller
{
    public function index(Request $request)
    {
        $deposits = DB::table('member_deposit_requests')->where('user_id', $request->user()->id)->orderByDesc('id')->limit(30)->get();
        $destinations = collect(['BEP20', 'TRC20'])->mapWithKeys(fn ($network) => [$network => DepositDestinationService::current($network)])->all();

        $transferSettings = MemberTransferPolicy::settings();
        $transferReceipt = DB::table('member_transfer_receipts as r')->join('users as u', 'u.id', '=', 'r.recipient_id')->where('r.sender_id', $request->user()->id)->where('r.id', session('transfer_receipt_id', 0))->select('r.*', 'u.username as recipient_username')->first();

        return view('member.deposit-transfer', compact('deposits', 'destinations', 'transferSettings', 'transferReceipt'));
    }

    public function deposit(Request $request)
    {
        $data = $request->validate(['amount' => ['required', 'regex:/\A\d+(?:\.\d{1,8})?\z/D'], 'network' => 'required|in:TRC20,BEP20', 'tx_hash' => 'required|alpha_num|between:20,150', 'receiving_address' => 'required|string|max:100', 'request_key' => 'required|regex:/\A[A-Za-z0-9_-]{8,100}\z/D']);
        try {
            VerifiedDepositService::submit((int) $request->user()->id, $data['amount'], $data['network'], $data['tx_hash'], $data['request_key'], $data['receiving_address']);
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['deposit' => 'This transaction may already be submitted, or the request could not be saved. Check your deposit history before retrying.']);
        } catch (Throwable $e) {
            return back()->withErrors(['deposit' => $e->getMessage()]);
        }

        return back()->with('status', 'Deposit evidence submitted for independent admin verification.');
    }

    public function transfer(Request $request)
    {
        return app(MemberActionController::class)->transfer($request);
    }
}
