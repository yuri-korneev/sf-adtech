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
    /**
     * Диапазон дат по параметру period: today | 7d | 30d | custom
     * Возвращает [from, to, period]
     */
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
                // по умолчанию уже задано
                break;
            case 'custom':
                $fromStr = $request->get('from');
                $toStr   = $request->get('to');
                if ($fromStr && $toStr) {
                    try {
                        $from = Carbon::parse($fromStr)->startOfDay();
                        $to   = Carbon::parse($toStr)->endOfDay();
                    } catch (\Throwable) {
                        // игнорируем парсинг-ошибку, оставляем дефолт
                    }
                }
                break;
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        return [$from, $to, $period];
    }

    /**
     * Страница статистики: таблица с агрегатами + итоги.
     * Разрез: day | month | year
     */
    public function stats(Request $request)
    {
        $group  = (string) $request->get('group', 'day'); // day|month|year
        $format = match ($group) {
            'month' => '%Y-%m-01',
            'year'  => '%Y-01-01',
            default => '%Y-%m-%d',
        };
        $commission = (float) config('sf.commission', 0.20);

        [$from, $to, $period] = $this->dateRange($request);
        $user = Auth::user();

        // офферы, на которые подписан текущий веб-мастер (список для фильтра)
        $offerIds = Subscription::where('webmaster_id', $user->id)->pluck('offer_id')->unique()->all();
        $offers   = Offer::whereIn('id', $offerIds ?: [0])->orderBy('name')->get(['id','name']);

        $offerId = $request->get('offer_id');

        // Собираем id подписок, по которым считаем статистику (опционально фильтруем по offer_id)
        $subIds = Subscription::where('webmaster_id', $user->id)
            ->when($offerId, fn($q) => $q->where('offer_id', $offerId))
            ->pluck('id')->all();

        // База: клики по нужным подпискам в выбранный период
        $base = DB::table('clicks as c')
            ->join('subscriptions as s', 's.id', '=', 'c.subscription_id')
            ->join('offers as o', 'o.id', '=', 's.offer_id')
            ->whereIn('c.subscription_id', $subIds ?: [0])
            ->whereBetween(DB::raw('COALESCE(c.clicked_at, c.created_at)'), [$from, $to]);

        // Агрегация по выбранному разрезу
        $rows = (clone $base)
            ->selectRaw("DATE_FORMAT(COALESCE(c.clicked_at, c.created_at), ?) AS d", [$format])
            ->selectRaw("COUNT(*) AS clicks_total")
            ->selectRaw("SUM(c.is_valid = 1) AS clicks_valid")
            // Доход WM = валидные клики * offer.cpc * (1 - commission)
            ->selectRaw("SUM(CASE WHEN c.is_valid = 1 THEN o.cpc * (1 - ?) ELSE 0 END) AS wm_revenue", [$commission])
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn($r) => [
                'date'         => $r->d,
                'clicks_total' => (int) $r->clicks_total,
                'clicks_valid' => (int) $r->clicks_valid,
                'wm_revenue'   => round((float) $r->wm_revenue, 2),
            ])
            ->all();

        // Итоги по периоду
        $totalsRow = (clone $base)
            ->selectRaw("COUNT(*) AS clicks_total")
            ->selectRaw("SUM(c.is_valid = 1) AS clicks_valid")
            ->selectRaw("SUM(CASE WHEN c.is_valid = 1 THEN o.cpc * (1 - ?) ELSE 0 END) AS wm_revenue", [$commission])
            ->first();

        $totals = [
            'clicks_total' => (int) ($totalsRow->clicks_total ?? 0),
            'clicks_valid' => (int) ($totalsRow->clicks_valid ?? 0),
            'wm_revenue'   => round((float) ($totalsRow->wm_revenue ?? 0), 2),
        ];

        return view('wm.stats', [
            'offers'    => $offers,
            'offerId'   => $offerId,
            'rows'      => $rows,
            'totals'    => $totals,
            'period'    => $period,
            'from'      => $from,
            'to'        => $to,
            'group'     => $group,
            'commission'=> $commission,
        ]);
    }

    /**
     * Данные для графика: labels + серии (total, valid, revenue)
     * Разрез: day | month | year
     */
    public function statsData(Request $request)
    {
        $group  = (string) $request->get('group', 'day'); // day|month|year
        $format = match ($group) {
            'month' => '%Y-%m-01',
            'year'  => '%Y-01-01',
            default => '%Y-%m-%d',
        };
        $commission = (float) config('sf.commission', 0.20);

        [$from, $to] = $this->dateRange($request);
        $user = Auth::user();
        $offerId = $request->get('offer_id');

        $subIds = Subscription::where('webmaster_id', $user->id)
            ->when($offerId, fn($q) => $q->where('offer_id', $offerId))
            ->pluck('id')->all();

        $rows = DB::table('clicks as c')
            ->join('subscriptions as s', 's.id', '=', 'c.subscription_id')
            ->join('offers as o', 'o.id', '=', 's.offer_id')
            ->whereIn('c.subscription_id', $subIds ?: [0])
            ->whereBetween(DB::raw('COALESCE(c.clicked_at, c.created_at)'), [$from, $to])
            ->selectRaw("DATE_FORMAT(COALESCE(c.clicked_at, c.created_at), ?) AS d", [$format])
            ->selectRaw("COUNT(*) AS clicks_total")
            ->selectRaw("SUM(c.is_valid = 1) AS clicks_valid")
            ->selectRaw("SUM(CASE WHEN c.is_valid = 1 THEN o.cpc * (1 - ?) ELSE 0 END) AS wm_revenue", [$commission])
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $labels = [];
        $total  = [];
        $valid  = [];
        $revenue= [];
        foreach ($rows as $r) {
            $labels[]  = $r->d;
            $total[]   = (int) $r->clicks_total;
            $valid[]   = (int) $r->clicks_valid;
            $revenue[] = (float) $r->wm_revenue;
        }

        return response()->json([
            'labels' => $labels,
            'series' => [
                'total'   => $total,
                'valid'   => $valid,
                'revenue' => $revenue,
            ],
        ])->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * CSV-экспорт (учитывает group и добавляет колонку Доход WM)
     * Разрез: day | month | year
     */
    public function statsCsv(Request $request)
{
    $group  = (string) $request->get('group', 'day'); // day|month|year
    $format = match ($group) {
        'month' => '%Y-%m-01',
        'year'  => '%Y-01-01',
        default => '%Y-%m-%d',
    };
    $commission = (float) config('sf.commission', 0.20);

    [$from, $to] = $this->dateRange($request);
    $user = Auth::user();
    $offerId = $request->get('offer_id');

    $subIds = Subscription::where('webmaster_id', $user->id)
        ->when($offerId, fn($q) => $q->where('offer_id', $offerId))
        ->pluck('id')->all();

    $rows = DB::table('clicks as c')
        ->join('subscriptions as s', 's.id', '=', 'c.subscription_id')
        ->join('offers as o', 'o.id', '=', 's.offer_id')
        ->whereIn('c.subscription_id', $subIds ?: [0])
        ->whereBetween(DB::raw('COALESCE(c.clicked_at, c.created_at)'), [$from, $to])
        ->selectRaw("DATE_FORMAT(COALESCE(c.clicked_at, c.created_at), ?) AS d", [$format])
        ->selectRaw("COUNT(*) AS clicks_total")
        ->selectRaw("SUM(c.is_valid = 1) AS clicks_valid")
        ->selectRaw("SUM(CASE WHEN c.is_valid = 1 THEN o.cpc * (1 - ?) ELSE 0 END) AS wm_revenue", [$commission])
        ->groupBy('d')
        ->orderBy('d')
        ->get();

    // Разделитель полей
    $delimiter = (string) config('sf.csv.delimiter', ';');

    $filename = 'wm_stats_' . $group . '_' . date('Y-m-d_His') . '.csv';
    $headers = [
        // ВАЖНО: отдаем как Windows-1251 — Excel откроет без «абракадабры»
        'Content-Type'        => 'text/csv; charset=Windows-1251',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
        'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
    ];

    // Итоги
    $totClicks = 0; $totValid = 0; $totRevenue = 0.0;
    foreach ($rows as $r) {
        $totClicks  += (int) $r->clicks_total;
        $totValid   += (int) $r->clicks_valid;
        $totRevenue += (float) $r->wm_revenue;
    }

    return response()->stream(function() use ($rows, $group, $delimiter, $totClicks, $totValid, $totRevenue) {
        // НЕ выводим BOM. Сразу даём подсказку Excel про разделитель:
        echo "sep={$delimiter}\r\n";

        $out = fopen('php://output', 'w');

        // хелпер для кодирования строки в CP1251 перед записью
        $write = function(array $fields) use ($out, $delimiter) {
            // конвертируем каждое поле из UTF-8 в Windows-1251
            $encoded = array_map(fn($v) => mb_convert_encoding((string)$v, 'Windows-1251', 'UTF-8'), $fields);
            fputcsv($out, $encoded, $delimiter);
        };

        // Заголовки
        $write(['Период ('.$group.')','Клики (всего)','Клики (валидные)','Доход WM']);

        foreach ($rows as $r) {
            // Формат периода как на экране
            $pretty = $r->d;
            try {
                if ($group === 'day')   { $pretty = \Illuminate\Support\Carbon::parse($r->d)->translatedFormat('d.m.Y'); }
                if ($group === 'month') { $pretty = \Illuminate\Support\Carbon::parse($r->d)->translatedFormat('m.Y'); }
                if ($group === 'year')  { $pretty = \Illuminate\Support\Carbon::parse($r->d)->translatedFormat('Y'); }
            } catch (\Throwable $e) {}

            // Числа: десятичная запятая, БЕЗ разделителя тысяч (чтобы Excel видел число)
            $rev = number_format((float) $r->wm_revenue, 2, ',', '');

            $write([
                $pretty,
                (int) $r->clicks_total,
                (int) $r->clicks_valid,
                $rev,
            ]);
        }

        // Итого — теми же правилами (без разделителя тысяч)
        $write([
            'Итого',
            (int) $totClicks,
            (int) $totValid,
            number_format((float) $totRevenue, 2, ',', ''),
        ]);

        fclose($out);
    }, 200, $headers);
}



}
