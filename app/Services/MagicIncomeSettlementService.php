<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Settles ONE source ID from a verified trading-profit funding against the existing 3% Magic reserve.
 * The source's contribution (3% of verified attributable profit) is split across its 20 lifetime ancestors.
 * Absent/ineligible/capped ancestors do NOT cause redistribution: their amounts remain held in the reserve.
 * All recipient wallet credits, counters, history and cap changes commit or roll back together.
 */
final class MagicIncomeSettlementService
{
    private const SCALE = 8;

    public static function settleSource(User $admin, int $fundingId, int $sourceUserId): array
    {
        if (config('leobot_magic.magic_matrix_settlement_enabled', false) !== true ||
            (app()->environment('production') && config('leobot_magic.production_financial_approval', false) !== true)) {
            throw new RuntimeException('Magic income settlement disabled pending explicit approval.');
        }
        if ($fundingId <= 0 || $sourceUserId <= 0) {
            throw new RuntimeException('Valid funding and source member required.');
        }
        return DB::transaction(function () use ($admin,$fundingId,$sourceUserId): array {
            $actor=DB::table('users')->where('id',$admin->getKey())->lockForUpdate()->first();
            if (!$actor || !in_array((string)$actor->role,['admin','super_admin'],true) ||
                $actor->status!=='active' || self::blocked($actor)) {
                throw new AuthorizationException('Active unblocked administrator required.');
            }
            // The single reserve row serializes ALL Magic settlement operations (also funding).
            $reserve=DB::table('profit_sharing_reserve_accounts')->where('id',1)->lockForUpdate()->first();
            $matrix=DB::table('magic_matrix_settings')->where('id',1)->lockForUpdate()->first();
            $global=DB::table('magic_income_settings')->where('id',1)->first();
            if (!$reserve || !$matrix || !$global || !self::enabled($matrix->settlement_enabled) ||
                !self::enabled($global->magic_enabled)) {
                throw new RuntimeException('Magic settlement approval switches are OFF.');
            }
            if ((string)$matrix->frequency!=='manual') {
                throw new RuntimeException('Only manual settlement is implemented; schedule review required.');
            }
            if ((int)$matrix->max_depth!==20) {
                throw new RuntimeException('Magic matrix must have exactly 20 levels.');
            }
            self::checkReserve($reserve);
            $funding=DB::table('profit_sharing_fundings')->where('id',$fundingId)->first();
            $source=DB::table('profit_sharing_self_reserves')
                ->where('funding_id',$fundingId)->where('user_id',$sourceUserId)->first();
            if (!$funding || !$source || bccomp((string)$funding->magic_percent_snapshot,'3',9)!==0) {
                throw new RuntimeException('Verified unified 3% Magic funding source not found.');
            }
            $sourceMember=DB::table('users')->where('id',$sourceUserId)->first();
            if (!$sourceMember || $sourceMember->status!=='active' || self::blocked($sourceMember)) {
                throw new RuntimeException('Blocked or inactive trading source cannot generate new Magic income.');
            }
            $already=DB::table('magic_income_entitlements')->where('funding_id',$fundingId)
                ->where('source_user_id',$sourceUserId)->orderBy('level')->get();
            if ($already->count()>0) {
                if ($already->count()!==20) throw new RuntimeException('Incomplete prior Magic settlement.');
                return ['already_settled'=>true,'credited_usdt'=>
                    $already->reduce(fn(string $carry, $r):string=>bcadd($carry,(string)$r->paid_amount,8),'0.00000000'),
                    'rows'=>20];
            }
            $rates=DB::table('magic_matrix_level_rates')->orderBy('level')->get();
            if ($rates->count()!==20) throw new RuntimeException('Exactly 20 rate rows required.');
            $sum='0.000000000';
            foreach ($rates as $i=>$rate) {
                if ((int)$rate->level!==$i+1 || !preg_match('/\A\d+(?:\.\d{1,9})?\z/D',(string)$rate->pool_percent)) {
                    throw new RuntimeException('Invalid or missing 20-level rate.');
                }
                $sum=bcadd($sum,(string)$rate->pool_percent,9);
            }
            if (bccomp($sum,'100',9)!==0) throw new RuntimeException('20 Magic rates must total exactly 100%.');
            $sourceProfit=(string)$source->member_profit_amount;
            self::validMoney($sourceProfit);
            $sourceMagic=bcdiv(bcmul($sourceProfit,'3',10),'100',8);
            // Expected every individual source budget to fit within the funding snapshot.
            if (bccomp($sourceMagic,(string)$funding->magic_amount,8)>0) {
                throw new RuntimeException('Source Magic entitlement exceeds funding reserve.');
            }
            $ancestors=MagicMatrixHierarchyService::ancestorsForMember($sourceUserId);
            $totalPaid='0.00000000';
            $totalNominal='0.00000000';
            $creditedCount=0;
            foreach ($rates as $rate) {
                $level=(int)$rate->level;
                $nominal=bcdiv(bcmul($sourceMagic,(string)$rate->pool_percent,17),'100',8);
                $totalNominal=bcadd($totalNominal,$nominal,8);
                $recipientId=$ancestors[$level]??null;
                $paid='0.00000000';
                $status='held_missing';
                $cycleId=null;
                if (bccomp($nominal,'0',8)===0) {
                    $status='held_dust';
                } elseif ($recipientId!==null) {
                    $member=DB::table('users')->where('id',$recipientId)->lockForUpdate()->first();
                    $cycle=DB::table('magic_matrix_cycles')->where('user_id',$recipientId)
                        ->orderByDesc('id')->lockForUpdate()->first();
                    if ($cycle) $cycleId=(int)$cycle->id;
                    if (!$member || $member->status!=='active' || self::blocked($member) ||
                        !$cycle || $cycle->status!=='active') {
                        $status='held_ineligible';
                    } else {
                        $remaining=bcsub((string)$cycle->earning_cap_usdt,(string)$cycle->magic_paid_usdt,8);
                        if (bccomp($remaining,'0',8)<0) throw new RuntimeException('Magic cap accounting inconsistent.');
                        if (bccomp($remaining,'0',8)===0) {
                            $status='held_cap';
                        } else {
                            $paid=bccomp($nominal,$remaining,8)>0 ? $remaining:$nominal;
                            $status=bccomp($paid,$nominal,8)===0 ? 'credited':'held_cap';
                            if (bccomp($reserve->magic_balance,bcadd($totalPaid,$paid,8),8)<0) {
                                throw new RuntimeException('Insufficient Magic funded reserve.');
                            }
                            $key='magic:unified:'.$fundingId.':'.$sourceUserId.':'.$level;
                            WalletService::credit((int)$recipientId,'available_balance',$paid,
                                'magic_income_settlement',$key,[
                                    'from_user_id'=>$sourceUserId,'level'=>$level,
                                    'reference_type'=>'profit_sharing_funding', 'reference_id'=>$fundingId,
                                    'description'=>'Verified 3% trading-profit Magic settlement, capped cycle'
                                ]);
                            // Lifetime income is a reporting total, never an extra spendable credit.
                            $lifetime=bcadd((string)$member->lifetime_income,$paid,8);
                            if (bccomp($lifetime,'999999999999.99999999',8)>0) {
                                throw new RuntimeException('Member lifetime income limit exceeded.');
                            }
                            DB::table('users')->where('id',$recipientId)->update(['lifetime_income'=>$lifetime,'updated_at'=>now()]);
                            $afterCycle=bcadd((string)$cycle->magic_paid_usdt,$paid,8);
                            DB::table('magic_matrix_cycles')->where('id',$cycle->id)->update([
                                'magic_paid_usdt'=>$afterCycle,
                                'status'=>bccomp($afterCycle,(string)$cycle->earning_cap_usdt,8)===0?'capped':'active',
                                'cap_reached_at'=>bccomp($afterCycle,(string)$cycle->earning_cap_usdt,8)===0?now():$cycle->cap_reached_at,
                                'updated_at'=>now(),
                            ]);
                            $totalPaid=bcadd($totalPaid,$paid,8);
                            $creditedCount++;
                        }
                    }
                }
                $held=bcsub($nominal,$paid,8);
                DB::table('magic_income_entitlements')->insert([
                    'funding_id'=>$fundingId,'source_user_id'=>$sourceUserId,'level'=>$level,
                    'beneficiary_user_id'=>$recipientId,'cycle_id'=>$cycleId,
                    'pool_percent_snapshot'=>(string)$rate->pool_percent,
                    'nominal_amount'=>$nominal,'paid_amount'=>$paid,'held_amount'=>$held,
                    'status'=>$status,
                    'transaction_key'=>'magic:unified:'.$fundingId.':'.$sourceUserId.':'.$level,
                    'approved_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            if (bccomp($totalNominal,$sourceMagic,8)>0) {
                throw new RuntimeException('Level distribution exceeds source budget.');
            }
            // Never authorize more entitlement than this funding's original Magic allocation.
            $historicalNominal='0.00000000';
            foreach (DB::table('magic_income_entitlements')->where('funding_id',$fundingId)
                         ->where('source_user_id','!=',$sourceUserId)->pluck('nominal_amount') as $oldAmount) {
                $historicalNominal=bcadd($historicalNominal,(string)$oldAmount,8);
            }
            if (bccomp(bcadd($historicalNominal,$totalNominal,8),(string)$funding->magic_amount,8)>0) {
                throw new RuntimeException('Funding-level Magic entitlement budgets exceed verified pool.');
            }
            $newPaid=bcadd((string)$reserve->total_magic_settled,$totalPaid,8);
            $newBalance=bcsub((string)$reserve->magic_balance,$totalPaid,8);
            if (bccomp($newBalance,'0',8)<0 ||
                bccomp(bcadd($newPaid,$newBalance,8),(string)$reserve->total_magic_funded,8)!==0) {
                throw new RuntimeException('Magic reserve reconciliation failed.');
            }
            DB::table('profit_sharing_reserve_accounts')->where('id',1)->update([
                'magic_balance'=>$newBalance,'total_magic_settled'=>$newPaid,'updated_at'=>now(),
            ]);
            return ['already_settled'=>false,'credited_usdt'=>$totalPaid,'credited_count'=>$creditedCount,
                    'held_usdt'=>bcsub($totalNominal,$totalPaid,8),'rows'=>20];
        },3);
    }

    private static function checkReserve(object $r): void {
        foreach (['magic_balance','total_magic_settled','total_magic_funded'] as $field) {
            self::validMoney((string)$r->{$field});
        }
        if (bccomp(bcadd((string)$r->magic_balance,(string)$r->total_magic_settled,8),
                (string)$r->total_magic_funded,8)!==0) {
            throw new RuntimeException('Magic reserve is not reconciled.');
        }
    }
    private static function validMoney(string $s): void {
        if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/D',$s) || bccomp($s,'0',8)<0) {
            throw new RuntimeException('Invalid financial amount.');
        }
    }
    private static function blocked(object $u): bool {
        return in_array(strtolower((string)($u->is_blocked??false)),['1','t','true'],true);
    }
    private static function enabled(mixed $v): bool {
        return in_array(strtolower((string)$v),['1','t','true','on'],true);
    }
}
