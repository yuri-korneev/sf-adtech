<?php

namespace App\Http\Controllers\Adv;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Click;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $data = $this->computeStats($request);
        $rows   = $data['rows'];
        $totals = $data['totals'];

        $filename = 'adv_stats_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function() use ($rows, $totals) {
            // BOM для корректного открытия в Excel
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Дата', 'Клики (всего)', 'Клики (валидные)', 'Стоимость (₽)']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['date'], $r['clicks_total'], $r['clicks_valid'], number_format((float)$r['cost'], 2, '.', '')]);
            }
            fputcsv($out, ['Итого', $totals['clicks_total'], $totals['clicks_valid'], number_format((float)$totals['cost'], 2, '.', '')]);
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
                    // нет данных — оставим дефолт 7 дней
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

        // Сводка по дням
        $clicks = Click::query()
            ->selectRaw('DATE(clicked_at) as d')
            ->selectRaw('COUNT(*) as clicks_total')
            ->selectRaw('SUM(CASE WHEN is_valid=1 THEN 1 ELSE 0 END) as clicks_valid')
            ->when($offerId, function($q) use ($offerId) {
                $q->whereHas('subscription', fn($w) => $w->where('offer_id', $offerId));
            }, function($q) use ($user) {
                $q->whereHas('subscription.offer', fn($w) => $w->where('advertiser_id', $user->id));
            })
            ->whereBetween('clicked_at', [$from, $to])
            ->groupBy('d')
            ->orderBy('d', 'asc')
            ->get();

        $daily  = [];
        $totals = ['clicks_total'=>0,'clicks_valid'=>0,'cost'=>0.0];

        if ($offerId) {
            $offer = $offers->firstWhere('id', (int)$offerId);
            $cpc = $offer?->cpc ? (float)$offer->cpc : 0.0;

            foreach ($clicks as $row) {
                $cost = (int)$row->clicks_valid * $cpc;
                $daily[] = [
                    'date'         => $row->d,
                    'clicks_total' => (int)$row->clicks_total,
                    'clicks_valid' => (int)$row->clicks_valid,
                    'cost'         => $cost,
                ];
                $totals['clicks_total'] += (int)$row->clicks_total;
                $totals['clicks_valid'] += (int)$row->clicks_valid;
                $totals['cost']         += $cost;
            }
        } else {
            // распределяем стоимость по дням с учётом CPC каждого оффера
            $byDayOffer = Click::query()
                ->selectRaw('DATE(clicked_at) as d, subscriptions.offer_id as oid')
                ->selectRaw('SUM(CASE WHEN clicks.is_valid=1 THEN 1 ELSE 0 END) as valid_cnt')
                ->join('subscriptions', 'subscriptions.id', '=', 'clicks.subscription_id')
                ->join('offers', 'offers.id', '=', 'subscriptions.offer_id')
                ->where('offers.advertiser_id', $user->id)
                ->whereBetween('clicked_at', [$from, $to])
                ->groupBy('d','oid')
                ->get();

            $cpcByOffer = $offers->pluck('cpc','id')->map(fn($v)=>(float)$v)->all();

            foreach ($clicks as $r) {
                $daily[$r->d] = [
                    'date'         => $r->d,
                    'clicks_total' => (int)$r->clicks_total,
                    'clicks_valid' => (int)$r->clicks_valid,
                    'cost'         => 0.0,
                ];
            }
            foreach ($byDayOffer as $r) {
                $cpc = $cpcByOffer[$r->oid] ?? 0.0;
                $daily[$r->d]['cost'] += (int)$r->valid_cnt * $cpc;
            }
            foreach ($daily as $row) {
                $totals['clicks_total'] += $row['clicks_total'];
                $totals['clicks_valid'] += $row['clicks_valid'];
                $totals['cost']         += $row['cost'];
            }
            $daily = array_values($daily);
            usort($daily, fn($a,$b)=>strcmp($a['date'],$b['date']));
        }

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
