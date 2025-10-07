<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    private function dateRange(Request $request): array
    {
        $period = $request->string('period')->toString() ?: '30d';
        $from = Carbon::today()->subDays(29)->startOfDay();
        $to   = Carbon::today()->endOfDay();

        switch ($period) {
            case 'today':
                $from = Carbon::today()->startOfDay();
                $to   = Carbon::today()->endOfDay();
                break;
            case '7d':
                $from = Carbon::today()->subDays(6)->startOfDay();
                $to   = Carbon::today()->endOfDay();
                break;
            case '30d':
                break;
            case 'custom':
                $fromStr = $request->get('from');
                $toStr   = $request->get('to');
                if ($fromStr && $toStr) {
                    try {
                        $from = Carbon::parse($fromStr)->startOfDay();
                        $to   = Carbon::parse($toStr)->endOfDay();
                    } catch (\Throwable) {}
                }
                break;
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        return [$from, $to, $period];
    }

    public function stats(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);
        $user = Auth::user();

        // офферы, на которые подписан текущий веб-мастер
        $offerIds = Subscription::where('webmaster_id', $user->id)->pluck('offer_id')->unique()->all();
        $offers   = Offer::whereIn('id', $offerIds ?: [0])->orderBy('name')->get(['id','name']);

        $offerId = $request->get('offer_id');
        $subIds = Subscription::where('webmaster_id', $user->id)
            ->when($offerId, fn($q) => $q->where('offer_id', $offerId))
            ->pluck('id')->all();

        $base = DB::table('clicks')
            ->whereIn('subscription_id', $subIds ?: [0])
            ->whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to]);

        $rows = (clone $base)->selectRaw("
                DATE(COALESCE(clicked_at, created_at)) AS d,
                COUNT(*) AS clicks_total,
                SUM(is_valid = 1) AS clicks_valid
            ")
            ->groupBy('d')->orderBy('d')->get()
            ->map(fn($r) => ['date'=>$r->d,'clicks_total'=>(int)$r->clicks_total,'clicks_valid'=>(int)$r->clicks_valid])
            ->all();

        $totals = (clone $base)->selectRaw("
            COUNT(*) AS clicks_total, SUM(is_valid = 1) AS clicks_valid
        ")->first();

        return view('wm.stats', [
            'offers'  => $offers,
            'offerId' => $offerId,
            'rows'    => $rows,
            'totals'  => [
                'clicks_total' => (int)($totals->clicks_total ?? 0),
                'clicks_valid' => (int)($totals->clicks_valid ?? 0),
            ],
            'period'  => $period,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    public function statsData(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $user = Auth::user();
        $offerId = $request->get('offer_id');

        $subIds = Subscription::where('webmaster_id', $user->id)
            ->when($offerId, fn($q) => $q->where('offer_id', $offerId))
            ->pluck('id')->all();

        $rows = DB::table('clicks')
            ->whereIn('subscription_id', $subIds ?: [0])
            ->whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to])
            ->selectRaw("
                DATE(COALESCE(clicked_at, created_at)) AS d,
                COUNT(*) AS clicks_total,
                SUM(is_valid = 1) AS clicks_valid
            ")
            ->groupBy('d')->orderBy('d')->get();

        $labels=[]; $total=[]; $valid=[];
        foreach ($rows as $r) { $labels[]=$r->d; $total[]=(int)$r->clicks_total; $valid[]=(int)$r->clicks_valid; }

        return response()->json([
            'labels'=>$labels,
            'series'=>['total'=>$total,'valid'=>$valid],
        ])->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0');
    }

    public function statsCsv(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $user = Auth::user();
        $offerId = $request->get('offer_id');

        $subIds = Subscription::where('webmaster_id', $user->id)
            ->when($offerId, fn($q) => $q->where('offer_id', $offerId))
            ->pluck('id')->all();

        $rows = DB::table('clicks')
            ->whereIn('subscription_id', $subIds ?: [0])
            ->whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to])
            ->selectRaw("
                DATE(COALESCE(clicked_at, created_at)) AS d,
                COUNT(*) AS clicks_total,
                SUM(is_valid = 1) AS clicks_valid
            ")
            ->groupBy('d')->orderBy('d')->get();

        $filename = 'wm_stats_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Дата','Клики (всего)','Клики (валидные)']);
            foreach ($rows as $r) { fputcsv($out, [$r->d, (int)$r->clicks_total, (int)$r->clicks_valid]); }
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }
}
