<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalRequestService;
use App\Services\WithdrawalPaymentConfirmationService;
use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

final class WithdrawalAdminController extends Controller
{
    public function index():View {
        return view('admin.withdrawals',[
            'settings'=>DB::table('withdrawal_settings')->where('id',1)->first(),
            'withdrawals'=>DB::table('withdrawal_requests')->orderByDesc('id')->paginate(30),
            'writeEnabled'=>config('leobot.withdrawals_enabled',false)===true &&
                (!app()->environment('production') || config('leobot.production_financial_approval',false)===true),
        ]);
    }
    public function approve(Request $request,int $id):RedirectResponse {
        try { WithdrawalRequestService::approve($request->user(),$id); }
        catch (RuntimeException|AuthorizationException $e) {return back()->withErrors(['withdrawal'=>$e->getMessage()]);}
        return back()->with('status','Withdrawal approved for EXTERNAL payment; no transfer has been made.');
    }
    /** Records payment only after independent chain verification; never broadcasts funds. */
    public function confirmExternalPayment(Request $request, int $id, WithdrawalPaymentConfirmationService $confirmation): RedirectResponse
    {
        $data = $request->validate([
            'network' => ['required', 'in:TRC20,BEP20'],
            'tx_hash' => ['required', 'string', 'max:66'],
        ]);
        try {
            $confirmation->confirm($request->user(), $id, $data['network'], $data['tx_hash']);
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['payment' => 'Payment not confirmed. Check transaction, recipient, amount, confirmations and authorization.']);
        }
        return back()->with('status', 'External USDT payment independently verified and recorded.');
    }

    public function reject(Request $request,int $id):RedirectResponse {
        try { WithdrawalRequestService::reject($request->user(),$id); }
        catch (RuntimeException|AuthorizationException $e) {return back()->withErrors(['withdrawal'=>$e->getMessage()]);}
        return back()->with('status','Withdrawal rejected and reserved earnings refunded.');
    }
    public function updateFee(Request $request):RedirectResponse {
        $data=$request->validate(['principal_fee'=>['required','regex:/\A\d+(?:\.\d{1,9})?\z/D']]);
        try {WithdrawalRequestService::setPrincipalFeePercent($request->user(),$data['principal_fee']);}
        catch (RuntimeException|\InvalidArgumentException|AuthorizationException $e) {
            return back()->withErrors(['fee'=>$e->getMessage()]);
        }
        return back()->with('status','Principal fee updated with audit; principal withdrawals remain disabled.');
    }
}
