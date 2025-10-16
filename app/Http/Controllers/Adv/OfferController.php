<?php

namespace App\Http\Controllers\Adv;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Click;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    /**
     * Список офферов текущего рекламодателя + поиск/фильтр.
     */
    public function index(Request $request)
    {
        $userId = auth()->id();

        $status = $request->get('status'); // null|active|inactive
        $q      = trim((string)$request->get('q', ''));

        $offers = Offer::query()
            ->where('advertiser_id', $userId)
            ->when($status === 'active', fn($qB) => $qB->where('is_active', true))
            ->when($status === 'inactive', fn($qB) => $qB->where('is_active', false))
            ->when($q !== '', function ($qB) use ($q) {
                $like = '%' . $q . '%';
                $qB->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                      ->orWhere('target_url', 'like', $like);
                });
            })
            ->with('topics:id,name') // колонка «Темы» без N+1
            ->withCount([
                // активные подписки веб-мастеров на оффер
                'subscriptions' => fn($w) => $w->where('is_active', true),
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $allOffers = Offer::query()
            ->where('advertiser_id', $userId)
            ->when($status === 'active', fn($qB) => $qB->where('is_active', true))
            ->when($status === 'inactive', fn($qB) => $qB->where('is_active', false))
            ->when($q !== '', function ($qB) use ($q) {
                $like = '%' . $q . '%';
                $qB->where(fn($w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('target_url', 'like', $like));
            })
            ->withCount('subscriptions')
            ->get();

        return view('adv.offers.index', [
            'offers' => $allOffers,   // используем для Kanban-разметки
            'status' => $status,
            'q'      => $q,
            'list'   => $offers,
        ]);
    }

    /** Форма создания */
    public function create()
    {
        $topics = Topic::orderBy('name')->get(['id','name']);
        return view('adv.offers.create', compact('topics'));
    }

    /** Сохранение */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required','string','max:255'],
            'cpc'        => ['required','numeric','min:0'],
            'target_url' => ['required','url','max:2048'],
            'is_active'  => ['nullable','boolean'],
            'topics'     => ['array'],
            'topics.*'   => ['integer','exists:topics,id'],
        ]);

        $offer = Offer::create([
            'advertiser_id' => auth()->id(),
            'name'          => $data['name'],
            'cpc'           => $data['cpc'],
            'target_url'    => $data['target_url'],
            'is_active'     => (bool)($data['is_active'] ?? true),
        ]);

        $offer->topics()->sync($data['topics'] ?? []);

        return redirect()->route('adv.offers.index')->with('status', 'Offer created');
    }

    /** Форма редактирования */
    public function edit(Offer $offer)
    {
        $this->authorizeOffer($offer);
        $topics = Topic::orderBy('name')->get(['id','name']);
        return view('adv.offers.edit', compact('offer', 'topics'));
    }

    /** Обновление */
    public function update(Request $request, Offer $offer)
    {
        $this->authorizeOffer($offer);

        $data = $request->validate([
            'name'       => ['required','string','max:255'],
            'cpc'        => ['required','numeric','min:0'],
            'target_url' => ['required','url','max:2048'],
            'is_active'  => ['nullable','boolean'],
            'topics'     => ['array'],
            'topics.*'   => ['integer','exists:topics,id'],
        ]);

        $offer->update([
            'name'       => $data['name'],
            'cpc'        => $data['cpc'],
            'target_url' => $data['target_url'],
            'is_active'  => (bool)($data['is_active'] ?? true),
        ]);

        $offer->topics()->sync($data['topics'] ?? []);

        return redirect()->route('adv.offers.index')->with('status', 'Offer updated');
    }

    /** Удаление */
    public function destroy(Offer $offer)
    {
        $this->authorizeOffer($offer);
        $offer->delete();

        return redirect()->route('adv.offers.index')->with('status', 'Offer deleted');
    }

    /** Подписки на конкретный оффер */
    public function subscriptions(Offer $offer)
    {
        $this->authorizeOffer($offer);

        $subs = $offer->subscriptions()
            ->with(['webmaster:id,name,email'])
            ->latest()
            ->paginate(20);

        return view('adv.offers.subscriptions', compact('offer', 'subs'));
    }

    /** Сводная статистика по офферам — /adv/offers/stats */
    public function offersStats(Request $request)
    {
        $user = $request->user();

        $offers = Offer::query()
            ->ofAdvertiser($user->id)
            ->withCount([
                'subscriptions',
                'clicks as clicks_count',
                'clicks as valid_clicks_count' => fn($q) => $q->where('is_valid', true),
            ])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('adv.offers.stats', compact('offers'));
    }

    /** Общая статистика рекламодателя — /adv/stats (view) */
    public function stats(Request $request)
    {
        $data = $this->computeStats($request);
        return view('adv.stats', $data);
    }

    /** CSV-выгрузка тех же данных — /adv/stats/csv */
    public function statsCsv(Request $request): StreamedResponse
    {
        $data       = $this->computeStats($request);
        $rows       = $data['rows'];    // ['date','clicks_total','clicks_valid','cost']
        $totals     = $data['totals'];  // ['clicks_total','clicks_valid','cost']
        $group      = (string) $request->get('group', 'day'); // day|month|year
        $commission = (float) config('sf.commission', 0.20);

        // Разделитель полей
        $delimiter = (string) config('sf.csv.delimiter', ';');

        $filename = 'adv_stats_' . $group . '_' . date('Y-m-d_His') . '.csv';
        $headers  = [
            // Windows-1251 БЕЗ BOM — Excel откроет без артефактов
            'Content-Type'        => 'text/csv; charset=Windows-1251',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ];

        return response()->stream(function () use ($rows, $totals, $commission, $group, $delimiter) {
            // Подсказка Excel про разделитель (BOM не печатаем)
            echo "sep={$delimiter}\r\n";
            $out = fopen('php://output', 'w');

            // helper: запись строки в CP1251
            $write = function (array $fields) use ($out, $delimiter) {
                $encoded = array_map(fn($v) => mb_convert_encoding((string)$v, 'Windows-1251', 'UTF-8'), $fields);
                fputcsv($out, $encoded, $delimiter);
            };

            // Заголовок
            $write([
                'Период (' . $group . ')',
                'Клики (всего)',
                'Клики (валидные)',
                'Расход, руб.',
                'Выплата WM, руб.',
                'Доход системы, руб.',
            ]);

            foreach ($rows as $r) {
                $advCost       = (float) ($r['cost'] ?? 0);
                $wmPayout      = $advCost * (1 - $commission);
                $systemRevenue = $advCost * $commission;

                // Формат «Период» как в таблице
                $pretty = $r['date'];
                try {
                    if ($group === 'day') {
                        $pretty = Carbon::parse($r['date'])->translatedFormat('d.m.Y');
                    }
                    if ($group === 'month') {
                        $pretty = Carbon::parse($r['date'])->translatedFormat('m.Y');
                    }
                    if ($group === 'year') {
                        $pretty = Carbon::parse($r['date'])->translatedFormat('Y');
                    }
                } catch (\Throwable $e) {
                }

                // Числа: запятая как десятичная, БЕЗ разделителя тысяч
                $write([
                    $pretty,
                    (int) ($r['clicks_total'] ?? 0),
                    (int) ($r['clicks_valid'] ?? 0),
                    number_format($advCost, 2, ',', ''),
                    number_format($wmPayout, 2, ',', ''),
                    number_format($systemRevenue, 2, ',', ''),
                ]);
            }

            // Итого — 1:1 со страницей
            $advCostTot       = (float) ($totals['cost'] ?? 0);
            $wmPayoutTot      = $advCostTot * (1 - $commission);
            $systemRevenueTot = $advCostTot * $commission;

            $write([
                'Итого',
                (int) ($totals['clicks_total'] ?? 0),
                (int) ($totals['clicks_valid'] ?? 0),
                number_format($advCostTot, 2, ',', ''),
                number_format($wmPayoutTot, 2, ',', ''),
                number_format($systemRevenueTot, 2, ',', ''),
            ]);

            fclose($out);
        }, 200, $headers);
    }

    /** Проверка владения оффером текущим пользователем */
    private function authorizeOffer(Offer $offer): void
    {
        if ($offer->advertiser_id !== auth()->id()) {
            abort(403);
        }
    }

    /**
     * Объединённая логика расчёта статистики для view и CSV.
     * Возвращает массив: offers, offerId, period, from, to, rows, totals, group.
     */
   private function computeStats(Request $request): array
    {
        // В консольных/внутренних вызовах Request::user() может быть null.
        // Достаём id безопасно: из auth()->id(), затем из Request::user(), иначе 401.
        $userId = auth()->id();
        if (!$userId) {
            $u = $request->user();
            $userId = $u?->id;
        }
        if (!$userId) {
            abort(401, 'Unauthorized');
        }

        // period: today | 7d (def) | 30d | 1y | all | custom
        $period = (string) $request->get('period', '7d');
        $from = \Illuminate\Support\Carbon::today()->subDays(6)->startOfDay();
        $to   = \Illuminate\Support\Carbon::today()->endOfDay();

        // Разрез: day|month|year
        $group  = (string) $request->get('group', 'day');
        $format = match ($group) {
            'month' => '%Y-%m-01',
            'year'  => '%Y-01-01',
            default => '%Y-%m-%d',
        };

        switch ($period) {
            case 'today':
                $from = \Illuminate\Support\Carbon::today()->startOfDay();
                $to   = \Illuminate\Support\Carbon::today()->endOfDay();
                break;
            case '30d':
                $from = \Illuminate\Support\Carbon::today()->subDays(29)->startOfDay();
                $to   = \Illuminate\Support\Carbon::today()->endOfDay();
                break;
            case '1y':
                $from = \Illuminate\Support\Carbon::today()->subDays(364)->startOfDay();
                $to   = \Illuminate\Support\Carbon::today()->endOfDay();
                break;
            case 'all':
                // Самая ранняя дата клика по офферам ТЕКУЩЕГО рекламодателя
                $minDate = \App\Models\Click::query()
                    ->join('subscriptions', 'subscriptions.id', '=', 'clicks.subscription_id')
                    ->join('offers', 'offers.id', '=', 'subscriptions.offer_id')
                    ->where('offers.advertiser_id', $userId)
                    ->min(DB::raw('COALESCE(clicks.clicked_at, clicks.created_at)'));
                if ($minDate) {
                    $from = \Illuminate\Support\Carbon::parse($minDate)->startOfDay();
                    $to   = \Illuminate\Support\Carbon::today()->endOfDay();
                } else {
                    $from = \Illuminate\Support\Carbon::today()->subDays(6)->startOfDay();
                    $to   = \Illuminate\Support\Carbon::today()->endOfDay();
                }
                break;
            case 'custom':
                $fromStr = $request->get('from');
                $toStr   = $request->get('to');
                if ($fromStr && $toStr) {
                    try {
                        $from = \Illuminate\Support\Carbon::parse($fromStr)->startOfDay();
                        $to   = \Illuminate\Support\Carbon::parse($toStr)->endOfDay();
                    } catch (\Throwable $e) {
                    }
                }
                break;
            case '7d':
            default:
                $from = \Illuminate\Support\Carbon::today()->subDays(6)->startOfDay();
                $to   = \Illuminate\Support\Carbon::today()->endOfDay();
                break;
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // Справочник офферов для фильтра
        $offers = \App\Models\Offer::where('advertiser_id', $userId)
            ->orderBy('name')
            ->get(['id','name','cpc']);

        $offerId = $request->get('offer_id'); // null → все офферы

        // Единый расчёт (по ТЗ): стоимость из offers.cpc для ВАЛИДНЫХ кликов
        $rows = DB::table('clicks as c')
            ->join('subscriptions as s', 's.id', '=', 'c.subscription_id')
            ->join('offers as o', 'o.id', '=', 's.offer_id')
            ->whereBetween(DB::raw('COALESCE(c.clicked_at, c.created_at)'), [$from, $to])
            ->where('o.advertiser_id', $userId)
            ->when($offerId, fn($q) => $q->where('o.id', (int)$offerId))
            ->selectRaw("DATE_FORMAT(COALESCE(c.clicked_at, c.created_at), ?) as d", [$format])
            ->selectRaw('COUNT(*) as clicks_total')
            ->selectRaw('SUM(c.is_valid=1) as clicks_valid')
            ->selectRaw('SUM(CASE WHEN c.is_valid=1 THEN o.cpc ELSE 0 END) as cost')
            ->groupBy('d')
            ->orderBy('d', 'asc')
            ->get();

        $daily  = [];
        $totals = ['clicks_total' => 0, 'clicks_valid' => 0, 'cost' => 0.0];

        foreach ($rows as $r) {
            $daily[] = [
                'date'         => $r->d,
                'clicks_total' => (int) $r->clicks_total,
                'clicks_valid' => (int) $r->clicks_valid,
                'cost'         => round((float) $r->cost, 2),
            ];
            $totals['clicks_total'] += (int) $r->clicks_total;
            $totals['clicks_valid'] += (int) $r->clicks_valid;
            $totals['cost']         += (float) $r->cost;
        }

        $totals['cost'] = round($totals['cost'], 2);

        return [
            'offers'  => $offers,
            'offerId' => $offerId,
            'period'  => $period,
            'from'    => $from,
            'to'      => $to,
            'rows'    => $daily,
            'totals'  => $totals,
            'group'   => $group,
        ];
    }


    public function setStatus(Request $request, Offer $offer)
    {
        $this->authorizeOffer($offer);
        $data = $request->validate([
            'status' => ['required','in:draft,active,paused,archived']
        ]);
        $offer->status = $data['status'];
        $offer->save();

        return response()->json(['ok' => true]);
    }
}
