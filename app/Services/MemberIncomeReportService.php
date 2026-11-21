<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Actual completed wallet income only. Reserves, deposits and transfers are never income. */
final class MemberIncomeReportService
{
    public static function entries(int $memberId): Builder
    {
        return DB::table('financial_ledger as l')->where('l.user_id', $memberId)
            ->where('l.wallet_type', 'available_balance')->where('l.status', 'completed')->where('l.amount', '>', 0)
            ->whereIn('l.entry_type', ['self_income_settlement', 'generation_income_settlement', 'magic_income_settlement']);
    }

    public static function summary(int $memberId, ?string $date = null, bool $magicOnly = false): array
    {
        $day = $date === null ? CarbonImmutable::now('Asia/Kolkata')->startOfDay() : CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Kolkata');
        $week = $day->startOfWeek();
        $month = $day->startOfMonth();
        $query = self::entries($memberId);
        if ($magicOnly) {
            $query->where('l.entry_type', 'magic_income_settlement');
        }
        $bounds = [];
        foreach ([[$day, $day->addDay()], [$week, $week->addWeek()], [$month, $month->addMonth()]] as [$start, $end]) {
            $bounds[] = $start->utc()->format('Y-m-d H:i:s');
            $bounds[] = $end->utc()->format('Y-m-d H:i:s');
        }
        $totals = $query->selectRaw('COALESCE(SUM(CASE WHEN l.created_at >= ? AND l.created_at < ? THEN l.amount ELSE 0 END),0) as daily_usdt,
            COALESCE(SUM(CASE WHEN l.created_at >= ? AND l.created_at < ? THEN l.amount ELSE 0 END),0) as weekly_usdt,
            COALESCE(SUM(CASE WHEN l.created_at >= ? AND l.created_at < ? THEN l.amount ELSE 0 END),0) as monthly_usdt,
            COALESCE(SUM(l.amount),0) as lifetime_usdt', $bounds)->first();

        return ['daily_usdt' => bcadd((string) $totals->daily_usdt, '0', 8), 'weekly_usdt' => bcadd((string) $totals->weekly_usdt, '0', 8),
            'monthly_usdt' => bcadd((string) $totals->monthly_usdt, '0', 8), 'lifetime_usdt' => bcadd((string) $totals->lifetime_usdt, '0', 8),
            'date' => $day->format('Y-m-d'), 'week_start' => $week->format('d/m/Y'), 'week_end' => $week->addDays(6)->format('d/m/Y')];
    }

    public static function history(int $memberId): Collection
    {
        return self::entries($memberId)
            ->leftJoin('profit_sharing_self_reserves as sr', function ($join): void {
                $join->on('sr.id', '=', 'l.reference_id')->where('l.reference_type', 'profit_sharing_self_reserve');
            })->leftJoin('generation_member_allocations as ga', function ($join): void {
                $join->on('ga.id', '=', 'l.reference_id')->where('l.reference_type', 'generation_member_allocation');
            })->leftJoin('profit_sharing_fundings as pf', 'pf.generation_funding_id', '=', 'ga.funding_id')
            ->leftJoin('staking_profit_statements as s', function ($join): void {
                $join->whereRaw("s.profit_sharing_funding_id = COALESCE(sr.funding_id, pf.id, CASE WHEN l.reference_type='profit_sharing_funding' THEN l.reference_id END)");
            })->leftJoin('income_distribution_requests as r', 'r.statement_id', '=', 's.id')
            ->orderByDesc('l.id')->limit(30)->get(['l.id', 'l.entry_type', 'l.amount', 'l.created_at', 'r.period_date', 'r.period_end_date', 'r.period']);
    }
}
