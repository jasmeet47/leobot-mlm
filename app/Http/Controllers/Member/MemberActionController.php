<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\MemberActionOtpService as Otp;
use App\Services\MemberFundTransferService;
use App\Services\MemberPaymentSecurity;
use App\Services\UsdtPaymentEvidence;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MemberActionController extends Controller
{
    private function walletData(Request $r): array
    {
        $data = $r->validate(['network' => 'required|in:BEP20,TRC20', 'address' => 'required|string|max:64']);
        $data['address'] = trim($data['address']);
        if ($data['network'] === 'BEP20') {
            $data['address'] = strtolower($data['address']);
        }UsdtPaymentEvidence::assertAddress($data['network'], $data['address']);

        return $data;
    }

    private function recipient(Request $r): object
    {
        $d = $r->validate(['recipient' => 'required|string|max:50']);
        $key = trim($d['recipient']);
        $rows = DB::table('users')->where(function ($q) use ($key): void {
            $q->whereRaw('lower(username)=?', [strtolower($key)]);
            if (ctype_digit($key)) {
                $q->orWhere('id', (int) $key);
            }
        })->where('role', 'member')->whereIn('status', ['active', 'inactive'])->where('is_blocked', false)->whereNull('deleted_at')->limit(2)->get(['id', 'username', 'name']);
        if ($rows->count() !== 1 || (int) $rows[0]->id === $r->user()->id) {
            throw ValidationException::withMessages(['recipient' => 'Enter another valid, unblocked member ID.']);
        }

        return $rows[0];
    }

    private function transferData(Request $r): array
    {
        $d = $r->validate(['amount' => ['required', 'string', 'max:25', 'regex:/\A\d+(?:\.\d{1,8})?\z/D'], 'request_key' => 'required|string|regex:/\A[A-Za-z0-9_-]{8,100}\z/D']);
        $person = $this->recipient($r);

        return ['recipient_id' => (int) $person->id, 'recipient_username' => $person->username, 'amount' => bcadd($d['amount'], '0', 8), 'request_key' => $d['request_key']];
    }

    public function verifyRecipient(Request $r): JsonResponse
    {
        $p = $this->recipient($r);

        return response()->json(['verified' => true, 'username' => $p->username, 'name' => $p->name])->header('Cache-Control', 'private, no-store');
    }

    public function walletSend(Request $r): JsonResponse
    {
        try {
            $id = Otp::issue($r->user(), 'profile_wallet', $this->walletData($r));

            return response()->json(['challenge_id' => $id, 'message' => 'Email OTP sent successfully. Enter it to save your address.']);
        } catch (QueryException $e) {
            report($e);

            return response()->json(['message' => 'Unable to prepare address verification.'], 422);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function walletSave(Request $r): JsonResponse
    {
        $proof = $r->validate(['otp_challenge_id' => 'required|integer|min:1', 'email_otp' => 'required|string|size:6']);
        try {
            $data = $this->walletData($r);
            $wallet = Otp::execute($r->user(), 'profile_wallet', $data, (int) $proof['otp_challenge_id'], $proof['email_otp'], function () use ($r, $data, $proof): int {
                $userId = $r->user()->id;
                $before = ['wallet_id' => MemberPaymentSecurity::selectedId($userId, $data['network'])];
                $old = DB::table('usdt_member_wallets')->where('user_id', $userId)->where('network', $data['network'])->where('address', $data['address'])->lockForUpdate()->first();
                $values = ['verified_at' => now(), 'revoked_at' => null, 'verification_method' => 'account_email_otp', 'proof_digest' => hash('sha256', 'email-otp:'.$userId.':'.$proof['otp_challenge_id']), 'updated_at' => now()];
                if ($old) {
                    DB::table('usdt_member_wallets')->where('id', $old->id)->update($values);
                    $id = (int) $old->id;
                } else {
                    $id = DB::table('usdt_member_wallets')->insertGetId($values + ['user_id' => $userId, 'network' => $data['network'], 'address' => $data['address'], 'created_at' => now()]);
                }
                MemberPaymentSecurity::select($userId, $id, $data['network'], $before);

                return $id;
            });

            return response()->json(['wallet_id' => $wallet, 'message' => 'Address changed successfully. आपका wallet address सफलतापूर्वक सेव हो गया है।']);
        } catch (QueryException $e) {
            report($e);

            return response()->json(['message' => 'Address could not be saved. Please retry.'], 422);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function transferSend(Request $r): JsonResponse
    {
        try {
            $data = $this->transferData($r);
            $id = Otp::issue($r->user(), 'fund_transfer', $data);

            return response()->json(['challenge_id' => $id, 'message' => 'Email OTP sent. Check the recipient and amount in your email.']);
        } catch (QueryException $e) {
            report($e);

            return response()->json(['message' => 'Unable to prepare transfer.'], 422);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function transfer(Request $r): RedirectResponse
    {
        $proof = $r->validate(['otp_challenge_id' => 'required|integer|min:1', 'email_otp' => 'required|string|size:6']);
        try {
            $d = $this->transferData($r);
            $id = Otp::execute($r->user(), 'fund_transfer', $d, (int) $proof['otp_challenge_id'], $proof['email_otp'], fn () => MemberFundTransferService::transfer($r->user()->id, $d['recipient_id'], $d['amount'], $d['request_key']));
        } catch (QueryException $e) {
            report($e);

            return back()->withInput($r->except('email_otp'))->withErrors(['transfer' => 'Transfer could not be saved. No partial debit was kept.']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withInput($r->except('email_otp'))->withErrors(['transfer' => $e->getMessage()]);
        }

        return redirect()->route('member.deposit-transfer.index')->with('status', 'Transfer successful. Fund transfer सफल हो गया है।')->with('transfer_receipt_id', $id);
    }
}
