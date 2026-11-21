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
            ->whereIn('l.entry_type', ['self_income_settlement', 'generation_income_settlement', 'magic_income_settlement'])
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('financial_ledger as reversal')->where('reversal.entry_type', 'income_correction_reversal')->where('reversal.status', 'completed')->where('reversal.reference_type', 'financial_ledger')->whereColumn('reversal.reference_id', 'l.id');
            });
    }

    public static function summary(int $memberId, ?string $date = null, bool $magicOnly = false, ?string $component = null): array
    {
        $day = $date === null ? CarbonImmutable::now('Asia/Kolkata')->startOfDay() : CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Kolkata');
        $week = $day->startOfWeek();
        $month = $day->startOfMonth();
        $query = self::entries($memberId);
        if ($magicOnly) {
            $query->where('l.entry_type', 'magic_income_settlement');
        }
        self::magicComponent($query, $component);
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

    public static function history(int $memberId, ?string $component = null): Collection
    {
        $query = self::entries($memberId);
        self::magicComponent($query, $component);

        return $query
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

    public static function selfTradingProfit(int $memberId, ?int $month = null, ?int $year = null): array
    {
        $today = CarbonImmutable::now('Asia/Kolkata');
        $weekStart = $today->startOfWeek();
        $monthStart = $today->setDate($year ?? $today->year, $month ?? $today->month, 1)->startOfDay();
        $query = self::entries($memberId)->where('l.entry_type', 'self_income_settlement');
        $weekly = (clone $query)->where('l.created_at', '>=', $weekStart->utc())->where('l.created_at', '<', $weekStart->addWeek()->utc())->sum('l.amount');
        $monthly = (clone $query)->where('l.created_at', '>=', $monthStart->utc())->where('l.created_at', '<', $monthStart->addMonth()->utc());
        $total = (clone $monthly)->sum('l.amount');
        $history = $monthly->leftJoin('profit_sharing_self_reserves as sr', function ($join): void {
            $join->on('sr.id', '=', 'l.reference_id')->where('l.reference_type', 'profit_sharing_self_reserve');
        })->leftJoin('staking_profit_statements as s', 's.profit_sharing_funding_id', '=', 'sr.funding_id')
            ->leftJoin('income_distribution_requests as r', 'r.statement_id', '=', 's.id')
            ->orderByDesc('l.created_at')->orderByDesc('l.id')
            ->paginate(20, ['l.id', 'l.amount', 'l.created_at', 'r.period_date', 'r.period_end_date'], 'self_profit_page')
            ->appends(['profit_month' => $monthStart->month, 'profit_year' => $monthStart->year]);

        return ['weekly_usdt' => bcadd((string) $weekly, '0', 8), 'monthly_usdt' => bcadd((string) $total, '0', 8),
            'week_start' => $weekStart->format('d/m/Y'), 'week_end' => $weekStart->addDays(6)->format('d/m/Y'),
            'month' => $monthStart->month, 'year' => $monthStart->year, 'month_label' => $monthStart->format('F Y'), 'history' => $history];
    }

    private static function magicComponent(Builder $query, ?string $component): void
    {
        if ($component === null) {
            return;
        }
        if (! in_array($component, ['matrix', 'equal'], true)) {
            throw new \InvalidArgumentException('Unknown Magic income component.');
        }
        $query->where('l.entry_type', 'magic_income_settlement');
        if ($component === 'equal') {
            $query->where('l.transaction_key', 'like', 'magic:equal:%');
        } else {
            $query->where(function (Builder $keys): void {
                $keys->whereNull('l.transaction_key')->orWhere('l.transaction_key', 'not like', 'magic:equal:%');
            });
        }
    }
}
