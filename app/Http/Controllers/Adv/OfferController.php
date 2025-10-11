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
            ->when($status === 'active',   fn($qB) => $qB->where('is_active', true))
            ->when($status === 'inactive', fn($qB) => $qB->where('is_active', false))
            ->when($q !== '', function ($qB) use ($q) {
                $like = '%'.$q.'%';
                $qB->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                      ->orWhere('target_url', 'like', $like);
                });
            })
            ->with('topics:id,name')           // для колонки «Темы» без N+1
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('adv.offers.index', [
            'offers' => $offers,
            'status' => $status,
            'q'      => $q,
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
        return view('adv.offers.edit', compact('offer','topics'));
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

        return view('adv.offers.subscriptions', compact('offer','subs'));
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
        $data    = $this->computeStats($request);
        $rows    = $data['rows'];
        $totals  = $data['totals'];

        $filename = 'adv_stats_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $commission = (float) config('sf.commission', 0.20);

        return response()->stream(function() use ($rows, $totals, $commission) {
            echo "\xEF\xBB\xBF"; // BOM для Excel
            $out = fopen('php://output', 'w');

            // Заголовки CSV
            fputcsv($out, ['Дата', 'Клики (всего)', 'Клики (валидные)', 'Расход, ₽', 'Выплата WM, ₽', 'Доход системы, ₽']);

            foreach ($rows as $r) {
                $advCost       = (float) $r['cost'];                        // сумма s.cpc по валидным кликам
                $systemRevenue = $advCost * $commission;
                $wmPayout      = $advCost * (1 - $commission);

                fputcsv($out, [
                    $r['date'],
                    (int) $r['clicks_total'],
                    (int) $r['clicks_valid'],
                    number_format($advCost, 2, '.', ''),
                    number_format($wmPayout, 2, '.', ''),
                    number_format($systemRevenue, 2, '.', ''),
                ]);
            }

            // Итого
            $advCostTot       = (float) $totals['cost'];
            $systemRevenueTot = $advCostTot * $commission;
            $wmPayoutTot      = $advCostTot * (1 - $commission);

            fputcsv($out, [
                'Итого',
                (int) $totals['clicks_total'],
                (int) $totals['clicks_valid'],
                number_format($advCostTot, 2, '.', ''),
                number_format($wmPayoutTot, 2, '.', ''),
                number_format($systemRevenueTot, 2, '.', ''),
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
     * Возвращает массив с ключами: offers, offerId, period, from, to, rows, totals.
     */
   private function computeStats(Request $request): array
    {
        $user = $request->user();

        // period: today | 7d (def) | 30d | all | custom
        $period = (string) $request->get('period', '7d');
        $from = Carbon::today()->subDays(6)->startOfDay();
        $to   = Carbon::today()->endOfDay();

        switch ($period) {
            case 'today':
                $from = Carbon::today()->startOfDay();
                $to   = Carbon::today()->endOfDay();
                break;

            case '30d':
                $from = Carbon::today()->subDays(29)->startOfDay();
                $to   = Carbon::today()->endOfDay();
                break;

            case 'all':
                // все время — находим самый ранний клик по офферам этого рекламодателя
                $minDate = Click::query()
                    ->join('subscriptions','subscriptions.id','=','clicks.subscription_id')
                    ->join('offers','offers.id','=','subscriptions.offer_id')
                    ->where('offers.advertiser_id', $user->id)
                    ->min('clicked_at');
                if ($minDate) {
                    $from = Carbon::parse($minDate)->startOfDay();
                    $to   = Carbon::today()->endOfDay();
                } else {
                    $from = Carbon::today()->subDays(6)->startOfDay();
                    $to   = Carbon::today()->endOfDay();
                }
                break;

            case 'custom':
                $fromStr = $request->get('from');
                $toStr   = $request->get('to');
                if ($fromStr && $toStr) {
                    try {
                        $from = Carbon::parse($fromStr)->startOfDay();
                        $to   = Carbon::parse($toStr)->endOfDay();
                    } catch (\Throwable $e) {}
                }
                break;

            case '7d':
            default:
                $from = Carbon::today()->subDays(6)->startOfDay();
                $to   = Carbon::today()->endOfDay();
                break;
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // Справочник офферов для фильтра
        $offers = Offer::where('advertiser_id', $user->id)
            ->orderBy('name')
            ->get(['id','name','cpc']);

        $offerId = $request->get('offer_id'); // null → все офферы

        // Единый расчёт по дням:
        // - clicks_total: COUNT(*)
        // - clicks_valid: SUM(is_valid)
        // - cost: SUM( s.cpc ) только по валидным кликам
        $rows = DB::table('clicks as c')
            ->join('subscriptions as s', 's.id', '=', 'c.subscription_id')
            ->join('offers as o', 'o.id', '=', 's.offer_id')
            ->whereBetween('c.clicked_at', [$from, $to])
            ->where('o.advertiser_id', $user->id)
            ->when($offerId, fn($q) => $q->where('o.id', (int)$offerId))
            ->selectRaw('DATE(c.clicked_at) as d')
            ->selectRaw('COUNT(*) as clicks_total')
            ->selectRaw('SUM(CASE WHEN c.is_valid=1 THEN 1 ELSE 0 END) as clicks_valid')
            ->selectRaw('SUM(CASE WHEN c.is_valid=1 THEN s.cpc ELSE 0 END) as cost')
            ->groupBy('d')
            ->orderBy('d','asc')
            ->get();

        $daily  = [];
        $totals = ['clicks_total'=>0, 'clicks_valid'=>0, 'cost'=>0.0];

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

        // Финальное округление totals
        $totals['cost'] = round($totals['cost'], 2);

        return [
            'offers'  => $offers,
            'offerId' => $offerId,
            'period'  => $period,
            'from'    => $from,
            'to'      => $to,
            'rows'    => $daily,
            'totals'  => $totals,
        ];
    }
}
