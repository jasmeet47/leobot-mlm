<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class IncomeDistributionService
{
    public static function weights(): array
    {
        return DB::table('staking_positions as p')->join('users as u', 'u.id', '=', 'p.user_id')->where('p.status', 'active')->where('u.role', 'member')->whereIn('u.status', ['active', 'inactive'])->where('u.is_blocked', false)->whereNull('u.deleted_at')->whereRaw('p.amount>p.withdrawn_amount')->whereExists(function ($q): void {
            $q->selectRaw('1')->from('financial_ledger as l')->whereColumn('l.user_id', 'p.user_id')->whereColumn('l.reference_id', 'p.id')->where('l.reference_type', 'staking_position')->where('l.wallet_type', 'staking_principal')->where('l.status', 'completed')->whereColumn('l.amount', 'p.amount')->whereRaw("l.transaction_key = 'staking:principal:' || p.request_key");
        })->groupBy('p.user_id')->orderBy('p.user_id')->selectRaw('p.user_id,SUM(p.amount-p.withdrawn_amount) as principal')->pluck('principal', 'p.user_id')->all();
    }

    public static function allocate(string $amount, array $weights): array
    {
        if (! preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0 || bccomp($amount, '999999999999.99999999', 8) > 0 || ! $weights || count($weights) > 1000) {
            throw new RuntimeException('Positive realized profit and at most 1000 funded stakers required.');
        }
        $total = '0';
        foreach ($weights as $weight) {
            if (bccomp((string) $weight, '0', 8) <= 0) {
                throw new RuntimeException('Positive principal weights required.');
            }
            $total = bcadd($total, bcmul((string) $weight, '100000000', 0), 0);
        }
        $units = bcmul($amount, '100000000', 0);
        $sum = '0';
        $rows = [];
        foreach ($weights as $id => $weight) {
            $n = bcmul($units, bcmul((string) $weight, '100000000', 0), 0);
            $u = bcdiv($n, $total, 0);
            $rows[(int) $id] = ['units' => $u, 'remainder' => bcmod($n, $total)];
            $sum = bcadd($sum, $u, 0);
        }
        $order = array_keys($rows);
        usort($order, fn ($a, $b) => bccomp($rows[$b]['remainder'], $rows[$a]['remainder'], 0) ?: $a <=> $b);
        $dust = (int) bcsub($units, $sum, 0);
        foreach (array_slice($order, 0, $dust) as $id) {
            $rows[$id]['units'] = bcadd($rows[$id]['units'], '1', 0);
        }
        $result = [];
        foreach ($rows as $id => $row) {
            if (bccomp($row['units'], '0', 0) > 0) {
                $result[$id] = bcdiv($row['units'], '100000000', 8);
            }
        }
        ksort($result, SORT_NUMERIC);

        return $result;
    }

    public static function submit(User $admin, string $amount, string $reference, string $period, string $date): int
    {
        if (! preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0 || bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new RuntimeException('Enter a valid positive realized profit amount.');
        }
        if (! in_array($period, ['daily', 'weekly', 'monthly'], true)) {
            throw new RuntimeException('Invalid distribution period.');
        }

        return DB::transaction(function () use ($admin, $amount, $reference, $period, $date): int {
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new RuntimeException('Active administrator required.');
            }
            $old = DB::table('staking_profit_statements as s')->join('income_distribution_requests as r', 'r.statement_id', '=', 's.id')->where('s.evidence_reference', strtoupper(trim($reference)))->select('s.*', 'r.period', 'r.period_date')->first();
            if ($old) {
                if ($old->period !== $period || substr($old->period_date, 0, 10) !== $date || (int) $old->submitted_by !== $admin->id || bccomp((string) $old->amount, $amount, 8) !== 0) {
                    throw new RuntimeException('Distribution reference reused with different details.');
                }

                return (int) $old->id;
            }
            if (DB::table('staking_profit_statements')->where('evidence_reference', strtoupper(trim($reference)))->exists()) {
                throw new RuntimeException('This receipt reference already belongs to a statement. Use its existing workflow.');
            }
            $shares = self::allocate($amount, self::weights());
            $id = StakingProfitService::submit($admin, $amount, $reference, $shares);
            if (DB::table('income_distribution_requests')->where('statement_id', $id)->exists()) {
                throw new RuntimeException('Statement already belongs to another distribution.');
            }
            DB::table('income_distribution_requests')->insert(['statement_id' => $id, 'period' => $period, 'period_date' => $date, 'submitted_by' => $admin->id, 'created_at' => now()]);

            return $id;
        });
    }

    public static function distribute(User $admin, int $statementId): void
    {
        if ((app()->environment('production') && (! config('leobot.production_financial_approval', false) || ! config('leobot_magic.production_financial_approval', false))) || ! config('leobot.self_income_settlement_enabled', false) || ! config('leobot.generation_income_settlement_enabled', false) || ! config('leobot_magic.magic_matrix_settlement_enabled', false)) {
            throw new RuntimeException('Income payout controls are OFF. Independent approval, funded reserves and separately approved payout controls are required; no payout was made.');
        }
        if (! DB::table('income_distribution_requests')->where('statement_id', $statementId)->exists()) {
            throw new RuntimeException('Distribution request not found.');
        }
        StakingProfitService::reserveReviewed($admin, $statementId);
        $statement = DB::table('staking_profit_statements')->find($statementId);
        $fund = DB::table('profit_sharing_fundings')->find($statement->profit_sharing_funding_id);
        GenerationAllocationService::allocate($admin, (int) $fund->generation_funding_id);
        DB::transaction(function () use ($admin, $fund, $statementId): void {
            foreach (DB::table('profit_sharing_self_reserves')->where('funding_id', $fund->id)->where('self_share_reserved', '>', 0)->get() as $r) {
                SelfIncomeSettlementService::settle($admin, (int) $r->id);
            }
            foreach (DB::table('generation_member_allocations')->where('funding_id', $fund->generation_funding_id)->where('classification', 'payable')->where('status', 'qualified_reserved_no_payout')->get() as $r) {
                GenerationIncomeSettlementService::settle($admin, (int) $r->id);
            }
            foreach (DB::table('staking_profit_allocations')->where('statement_id', $statementId)->get() as $r) {
                MagicIncomeSettlementService::settleSource($admin, (int) $fund->id, (int) $r->user_id);
            }
        });
    }
}
