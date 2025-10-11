<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatsService
{
    protected float $commission;

    public function __construct()
    {
        $this->commission = (float) config('sf.commission', 0.20);
    }

    // Доходы WM по дням (считаем по ставке подписки s.cpc)
    public function wmDailyIncome(int $wmId, Carbon $from, Carbon $to): Collection
    {
        return DB::table('clicks as c')
            ->join('subscriptions as s', 's.id', '=', 'c.subscription_id')
            ->where('s.webmaster_id', $wmId)
            ->whereBetween('c.clicked_at', [$from, $to])
            ->selectRaw('DATE(c.clicked_at) d,
                        SUM(CASE WHEN c.is_valid=1 THEN s.cpc ELSE 0 END) as income')
            ->groupBy('d')
            ->orderBy('d')
            ->get();
    }

    // Финансовые метрики для ADV/ADMIN по дням
    public function advDailyFinancials(?int $advId, Carbon $from, Carbon $to): Collection
    {
        $q = DB::table('clicks as c')
            ->join('subscriptions as s', 's.id', '=', 'c.subscription_id')
            ->join('offers as o', 'o.id', '=', 's.offer_id')
            ->whereBetween('c.clicked_at', [$from, $to]);

        if ($advId) {
            $q->where('o.advertiser_id', $advId);
        }

        return $q->selectRaw(
                'DATE(c.clicked_at) d,
                 SUM(CASE WHEN c.is_valid=1 THEN s.cpc ELSE 0 END)             as adv_cost,
                 SUM(CASE WHEN c.is_valid=1 THEN s.cpc ELSE 0 END) * ?         as system_revenue,
                 SUM(CASE WHEN c.is_valid=1 THEN s.cpc ELSE 0 END) * (1 - ?)   as wm_payout',
                [$this->commission, $this->commission]
            )
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(function ($row) {
                $row->adv_cost       = round((float)$row->adv_cost, 2);
                $row->system_revenue = round((float)$row->system_revenue, 2);
                $row->wm_payout      = round((float)$row->wm_payout, 2);
                return $row;
            });
    }

    // Свод за период (карточки)
    public function advTotals(?int $advId, Carbon $from, Carbon $to): array
    {
        $rows = $this->advDailyFinancials($advId, $from, $to);
        $tot = ['adv_cost'=>0.0,'system_revenue'=>0.0,'wm_payout'=>0.0];
        foreach ($rows as $r) {
            $tot['adv_cost']       += $r->adv_cost;
            $tot['system_revenue'] += $r->system_revenue;
            $tot['wm_payout']      += $r->wm_payout;
        }
        return array_map(fn($v)=> round($v,2), $tot);
    }
}
