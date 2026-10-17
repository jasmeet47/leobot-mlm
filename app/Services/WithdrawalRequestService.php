<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Earnings withdrawals only (available_balance), with hold, approval, rejection/refund.
 * External payment is never triggered, claimed, or assumed by this service.
 * Principal release is explicitly blocked until a separate reconciled principal liability exists.
 */
final class WithdrawalRequestService
{
    private static function gate(string $setting): void {
        if (config('leobot.withdrawals_enabled',false)!==true ||
            (app()->environment('production') && config('leobot.production_financial_approval',false)!==true)) {
            throw new RuntimeException('Withdrawals are disabled pending production approval.');
        }
        $s=DB::table('withdrawal_settings')->where('id',1)->first();
        if (!$s || !self::enabled($s->{$setting}??false)) {
            throw new RuntimeException('Withdrawal operation is disabled in admin settings.');
        }
    }
    public static function request(User $member,string $amount,string $requestKey,string $kind='earnings',?int $usdtWalletId=null): int {
        self::gate('member_requests_enabled');
        if ($kind!=='earnings') {
            throw new RuntimeException('Principal withdrawals require verified principal release ledger; unavailable.');
        }
        self::money($amount);
        self::reference($requestKey);
        if ($usdtWalletId === null || $usdtWalletId < 1) {
            throw new RuntimeException('Verified USDT payout wallet is required.');
        }
        return DB::transaction(function()use($member,$amount,$requestKey,$usdtWalletId):int{
            $user=DB::table('users')->where('id',$member->getKey())->lockForUpdate()->first();
            if (!$user || $user->status!=='active' || self::blocked($user)) {
                throw new AuthorizationException('Active unblocked member required.');
            }
            $key=strtoupper($requestKey);
            $old=DB::table('withdrawal_requests')->where('request_key',$key)->first();
            if ($old) {
                if ((int)$old->user_id!==(int)$user->id ||
                    bccomp((string)$old->gross_amount,$amount,8)!==0 ||
                    $old->wallet_type!=='available_balance' ||
                    (int)($old->usdt_member_wallet_id ?? 0)!==$usdtWalletId ||
                    empty($old->locked_payment_address)) {
                    throw new RuntimeException('Withdrawal request key reused with different details.');
                }
                return (int)$old->id;
            }
            // Row lock and wallet ownership are checked before any balance is held.
            $wallet=UsdtVerifiedWallet::forWithdrawal((int)$user->id,$usdtWalletId);
            WalletService::debit((int)$user->id,'available_balance',$amount,
                'withdrawal_hold','withdrawal:hold:'.$key,
                ['description'=>'Held pending administrator verification']);
            $id=DB::table('withdrawal_requests')->insertGetId([
                'user_id'=>$user->id,'request_key'=>$key,'wallet_type'=>'available_balance',
                'gross_amount'=>$amount,'fee_amount'=>'0.00000000',
                'net_amount'=>$amount,'status'=>'requested',
                'usdt_member_wallet_id'=>$usdtWalletId,
                'payment_network'=>$wallet->network,
                'locked_payment_address'=>$wallet->address,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            self::event($id,(int)$user->id,'requested');
            return (int)$id;
        },3);
    }

    public static function approve(User $admin,int $withdrawalId): void {
        self::gate('admin_review_enabled');
        DB::transaction(function()use($admin,$withdrawalId):void{
            $actor=self::admin($admin);
            $w=DB::table('withdrawal_requests')->where('id',$withdrawalId)->lockForUpdate()->first();
            if (!$w || $w->status!=='requested') throw new RuntimeException('Withdrawal not pending.');
            // Protected hold has already debited the member wallet. No second debit.
            if ((int)$w->user_id===(int)$actor->id) throw new AuthorizationException('Self-approval denied.');
            DB::table('withdrawal_requests')->where('id',$withdrawalId)->update([
                'status'=>'approved_awaiting_payment','reviewed_by'=>$actor->id,
                'reviewed_at'=>now(),'approved_at'=>now(),'updated_at'=>now(),
            ]);
            self::event($withdrawalId,(int)$actor->id,'approved_awaiting_payment');
        },3);
    }

    public static function reject(User $admin,int $withdrawalId): void {
        self::gate('admin_review_enabled');
        DB::transaction(function()use($admin,$withdrawalId):void{
            $actor=self::admin($admin);
            $w=DB::table('withdrawal_requests')->where('id',$withdrawalId)->lockForUpdate()->first();
            if (!$w || !in_array($w->status,['requested','approved_awaiting_payment'],true)) {
                throw new RuntimeException('Only unpaid withdrawals can be rejected.');
            }
            WalletService::credit((int)$w->user_id,'available_balance',(string)$w->gross_amount,
                'withdrawal_refund','withdrawal:refund:'.$w->request_key,
                ['description'=>'Rejected withdrawal hold returned']);
            DB::table('withdrawal_requests')->where('id',$withdrawalId)->update([
                'status'=>'rejected','reviewed_by'=>$actor->id,'reviewed_at'=>now(),
                'updated_at'=>now(),
            ]);
            self::event($withdrawalId,(int)$actor->id,'rejected_and_refunded');
        },3);
    }

    /** Settings update does not toggle any payout switch. */
    public static function setPrincipalFeePercent(User $admin,string $percent):void {
        if (config('leobot.withdrawal_settings_write_enabled',false)!==true ||
            (app()->environment('production') && config('leobot.production_financial_approval',false)!==true)) {
            throw new RuntimeException('Withdrawal policy edits disabled.');
        }
        if (!preg_match('/\A\d+(?:\.\d{1,9})?\z/D',$percent) ||
            bccomp($percent,'0',9)<0 || bccomp($percent,'100',9)>0) {
            throw new InvalidArgumentException('Fee must be 0–100%.');
        }
        DB::transaction(function()use($admin,$percent):void{
            $actor=self::admin($admin);
            $s=DB::table('withdrawal_settings')->where('id',1)->lockForUpdate()->first();
            if (!$s) throw new RuntimeException('Withdrawal settings missing.');
            DB::table('withdrawal_settings')->where('id',1)->update([
                'principal_withdrawal_fee_percent'=>$percent,'updated_at'=>now(),
            ]);
            DB::table('withdrawal_settings_audits')->insert([
                'admin_user_id'=>$actor->id,'action'=>'principal_fee_changed',
                'old_value'=>(string)$s->principal_withdrawal_fee_percent,
                'new_value'=>$percent,'created_at'=>now(),
            ]);
        },3);
    }
    private static function admin(User $admin):object {
        $a=DB::table('users')->where('id',$admin->getKey())->lockForUpdate()->first();
        if (!$a || !in_array($a->role,['admin','super_admin'],true) ||
            $a->status!=='active' || self::blocked($a)) {
            throw new AuthorizationException('Authorized unblocked administrator required.');
        }
        return $a;
    }
    private static function money(string $v):void {
        if (!preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D',$v) ||
            bccomp($v,'0',8)<=0 || bccomp($v,'999999999999.99999999',8)>0) {
            throw new InvalidArgumentException('Invalid withdrawal amount.');
        }
    }
    private static function reference(string $v):void {
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{2,99}\z/D',$v)) {
            throw new InvalidArgumentException('Invalid idempotency reference.');
        }
    }
    private static function event(int $wid,int $actor,string $type):void {
        DB::table('withdrawal_events')->insert([
            'withdrawal_request_id'=>$wid,'actor_user_id'=>$actor,
            'event_type'=>$type,'created_at'=>now(),
        ]);
    }
    private static function blocked(object $a):bool {
        return in_array(strtolower((string)($a->is_blocked??false)),['1','t','true'],true);
    }
    private static function enabled(mixed $a):bool {
        return in_array(strtolower((string)$a),['1','true','t','on'],true);
    }
}
