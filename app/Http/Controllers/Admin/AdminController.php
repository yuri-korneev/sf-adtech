<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Click;
use App\Models\Offer;
use App\Models\Subscription;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /** Вспомогательно: диапазон дат по GET ?period= */
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
                    } catch (\Throwable $e) {
                        // оставим дефолт
                    }
                }
                break;
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        return [$from, $to, $period];
    }

    /** GET /admin — дашборд */
    public function dashboard(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);

        $counts = [
            'users' => [
                'total'      => User::count(),
                'admin'      => User::where('role', 'admin')->count(),
                'advertiser' => User::where('role', 'advertiser')->count(),
                'webmaster'  => User::where('role', 'webmaster')->count(),
                'active'     => User::where('is_active', true)->count(),
            ],
            'offers' => [
                'total'  => Offer::count(),
                'active' => Offer::where('is_active', true)->count(),
            ],
            'subs' => Subscription::count(),
            'clicks' => [
                'total'   => Click::whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to])->count(),
                'valid'   => Click::whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to])->where('is_valid', true)->count(),
                'refused' => Click::whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to])
                                  ->where('is_valid', false)
                                  ->whereIn('invalid_reason', ['not_subscribed','inactive'])
                                  ->count(),
            ],
            'range' => [$from, $to, $period],
        ];

        $latestClicks = Click::with(['subscription.offer:id,name', 'subscription.webmaster:id,name'])
            ->whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to])
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('counts', 'latestClicks'));
    }

    /** GET /admin/users — список пользователей */
    public function users(Request $request)
    {
        $q = User::query()->orderBy('id');

        if ($search = trim((string)$request->get('q'))) {
            $q->where(function ($w) use ($search) {
                $w->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }
        if ($role = $request->get('role')) {
            $q->where('role', $role);
        }

        $users = $q->paginate(15)->withQueryString();

        return view('admin.users', compact('users'));
    }

    /** POST /admin/users/{user}/toggle — вкл/выкл пользователя */
    public function toggleUser(User $user)
    {
        $user->is_active = ! (bool) $user->is_active;
        $user->save();

        return back()->with('status', 'Состояние пользователя обновлено');
    }

    /** GET /admin/offers — список офферов */
    public function offers(Request $request)
    {
        $q = Offer::query()
            ->with(['advertiser:id,name,email', 'topics:id,name'])
            ->withCount([
                'subscriptions',
                'clicks as clicks_count',
                'clicks as valid_clicks_count' => fn($w) => $w->where('is_valid', true),
            ])
            ->orderByDesc('id');

        // Поиск по названию/URL
        if ($search = trim((string)$request->get('q'))) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('target_url', 'like', "%{$search}%");
            });
        }

        // Фильтр по активности (только is_active)
        if (null !== ($active = $request->get('active'))) {
            if ($active === '1' || $active === '0') {
                $q->where('is_active', $active === '1');
            }
        }

        // Фильтр по теме: /admin/offers?topic=<id>
        $topicId = $request->get('topic');
        if ($topicId) {
            $q->whereHas('topics', fn($w) => $w->where('topics.id', $topicId));
        }

        $offers = $q->paginate(15)->withQueryString();
        $topics = Topic::orderBy('name')->get(['id','name']);

        return view('admin.offers', [
            'offers'  => $offers,
            'topics'  => $topics,
            'q'       => $request->get('q'),
            'active'  => $request->get('active'),
            'topicId' => $topicId,
        ]);
    }

    /** POST /admin/offers/{offer}/toggle — вкл/выкл оффер */
    public function toggleOffer(Offer $offer)
    {
        $offer->is_active = ! (bool) $offer->is_active;
        $offer->save();

        return back()->with('status', 'Состояние оффера обновлено');
    }

    /** GET /admin/clicks — список кликов */
    public function clicks(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);

        $q = Click::query()
            ->with(['subscription.offer:id,name', 'subscription.webmaster:id,name'])
            ->whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to])
            ->orderByDesc(DB::raw('COALESCE(clicked_at, created_at)'));

        // Поиск по токену
        if ($token = trim((string)$request->get('q'))) {
            $q->where('token', 'like', "%{$token}%");
        }

        // Фильтр type: all|valid|refused
        $type = $request->string('type', 'all')->toString();
        switch ($type) {
            case 'valid':
                $q->where('is_valid', true);
                break;
            case 'refused':
                $q->where('is_valid', false)->whereIn('invalid_reason', ['not_subscribed','inactive']);
                break;
            case 'all':
            default:
                // нет доп.условий
                break;
        }

        // Back-compat: valid=0|1 (если type=all)
        if (($valid = $request->get('valid')) !== null && $type === 'all') {
            if ($valid === '1') $q->where('is_valid', true);
            if ($valid === '0') $q->where('is_valid', false);
        }

        $clicks = $q->paginate(20)->withQueryString();
        return view('admin.clicks', compact('clicks', 'period', 'from', 'to', 'type'));
    }

    /** GET /admin/clicks/stats — JSON для графиков */
    public function clicksStats(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);

        $type = $request->string('type', 'all')->toString();

        $base = DB::table('clicks')
            ->whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to]);

        switch ($type) {
            case 'valid':
                $base->where('is_valid', 1);
                break;
            case 'refused':
                $base->where('is_valid', 0)->whereIn('invalid_reason', ['not_subscribed','inactive']);
                break;
            case 'all':
            default:
                break;
        }

        // Back-compat: valid=0|1 (если type=all)
        $valid = $request->string('valid')->value();
        if ($valid === '1' && $type === 'all')       $base->where('is_valid', 1);
        elseif ($valid === '0' && $type === 'all')   $base->where('is_valid', 0);

        $rows = (clone $base)
            ->selectRaw("
                DATE(COALESCE(clicked_at, created_at)) AS d,
                SUM(is_valid = 1)                                       AS valid_cnt,
                SUM(is_valid = 0)                                       AS invalid_cnt,
                SUM(is_valid = 0 AND invalid_reason IN ('not_subscribed','inactive')) AS refused_cnt
            ")
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $labels = $validArr = $invalidArr = $refusedArr = [];
        foreach ($rows as $r) {
            $labels[]     = $r->d;
            $validArr[]   = (int) $r->valid_cnt;
            $invalidArr[] = (int) $r->invalid_cnt;
            $refusedArr[] = (int) $r->refused_cnt;
        }

        $tot = (clone $base)->selectRaw("
            SUM(is_valid = 1)                                       AS valid,
            SUM(is_valid = 0)                                       AS invalid,
            SUM(is_valid = 0 AND invalid_reason IN ('not_subscribed','inactive')) AS refused
        ")->first();

        return response()->json([
            'labels' => $labels,
            'series' => [
                'valid'   => $validArr,
                'invalid' => $invalidArr,
                'refused' => $refusedArr,
            ],
            'totals' => [
                'valid'   => (int) ($tot->valid   ?? 0),
                'invalid' => (int) ($tot->invalid ?? 0),
                'refused' => (int) ($tot->refused ?? 0),
                'all'     => (int) (($tot->valid ?? 0) + ($tot->invalid ?? 0)),
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /** GET /admin/clicks/csv — CSV выгрузка кликов */
    public function clicksCsv(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);

        $q = Click::query()
            ->with(['subscription.offer:id,name', 'subscription.webmaster:id,name'])
            ->whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to])
            ->orderByDesc(DB::raw('COALESCE(clicked_at, created_at)'));

        if ($token = trim((string)$request->get('q'))) {
            $q->where('token', 'like', "%{$token}%");
        }

        $type = $request->string('type', 'all')->toString();
        switch ($type) {
            case 'valid':
                $q->where('is_valid', true);
                break;
            case 'refused':
                $q->where('is_valid', false)->whereIn('invalid_reason', ['not_subscribed','inactive']);
                break;
            case 'all':
            default:
                break;
        }

        if (($valid = $request->get('valid')) !== null && $type === 'all') {
            if ($valid === '1') $q->where('is_valid', true);
            if ($valid === '0') $q->where('is_valid', false);
        }

        $clicks = $q->get();

        $delimiter = (string) config('sf.csv.delimiter', ';');
        $filename  = 'admin_clicks_' . date('Y-m-d_His') . '.csv';
        $headers   = [
            'Content-Type'        => 'text/csv; charset=Windows-1251',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ];

        return response()->stream(function () use ($clicks, $delimiter) {
            echo "sep={$delimiter}\r\n";
            $out = fopen('php://output', 'w');

            $write = function (array $fields) use ($out, $delimiter) {
                $encoded = array_map(fn($v) => mb_convert_encoding((string)$v, 'Windows-1251', 'UTF-8'), $fields);
                fputcsv($out, $encoded, $delimiter);
            };

            // Заголовок
            $write(['ID','Оффер','Веб-мастер','Токен','Валиден','Причина','Клик','Редирект','IP','Браузер','Реферер']);

            foreach ($clicks as $c) {
                $clicked = ($c->clicked_at ?? $c->created_at);
                $write([
                    (int) $c->id,
                    $c->subscription->offer->name ?? '',
                    $c->subscription->webmaster->name ?? '',
                    (string) $c->token,
                    $c->is_valid ? 'Да' : 'Нет',
                    (string) ($c->invalid_reason ?? ''),
                    $clicked ? $clicked->format('d.m.Y H:i:s') : '',
                    $c->redirected_at ? $c->redirected_at->format('d.m.Y H:i:s') : '',
                    (string) ($c->ip ?? ''),
                    (string) ($c->user_agent ?? ''),   // Браузер
                    (string) ($c->referer ?? ''),      // Реферер  ← тут
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    /** GET /admin/revenue/stats — JSON для графиков выручки */
    public function revenueStats(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);

        // Грануляция: day|month|year
        $group = $request->string('group', 'day')->toString();
        $format = match ($group) {
            'month' => '%Y-%m',
            'year'  => '%Y',
            default => '%Y-%m-%d',
        };

        $commission = (float) config('sf.commission', 0.20);

        $base = DB::table('clicks as c')
            ->leftJoin('subscriptions as s', 's.id', '=', 'c.subscription_id')
            ->leftJoin('offers as o', 'o.id', '=', 's.offer_id')
            ->whereBetween(DB::raw('COALESCE(c.clicked_at, c.created_at)'), [$from, $to])
            ->where('c.is_valid', 1);

        // Приоритет: значения в clicks; иначе — offers.cpc и комиссия.
        $rows = (clone $base)
            ->selectRaw("DATE_FORMAT(COALESCE(c.clicked_at, c.created_at), ?) as g", [$format])
            ->selectRaw("COUNT(*) AS clicks_valid")
            ->selectRaw("SUM(COALESCE(c.adv_cost, o.cpc)) AS spend_total")
            ->selectRaw("SUM(COALESCE(c.wm_payout, COALESCE(c.adv_cost, o.cpc) * (1 - ?))) AS wm_total", [$commission])
            ->groupBy('g')
            ->orderBy('g')
            ->get();

        $labels = $clicks = $spend = $sys = $wm = [];
        foreach ($rows as $r) {
            $labels[] = $r->g;
            $clicks[] = (int) $r->clicks_valid;
            $spend[]  = (float) $r->spend_total;
            $wm[]     = (float) $r->wm_total;
            $sys[]    = max(0.0, (float)$r->spend_total - (float)$r->wm_total);
        }

        $tot = (clone $base)
            ->selectRaw("COUNT(*) AS clicks_valid")
            ->selectRaw("SUM(COALESCE(c.adv_cost, o.cpc)) AS spend_total")
            ->selectRaw("SUM(COALESCE(c.wm_payout, COALESCE(c.adv_cost, o.cpc) * (1 - ?))) AS wm_total", [$commission])
            ->first();

        return response()->json([
            'labels' => $labels,
            'series' => [
                'clicks' => $clicks,
                'spend'  => $spend,
                'system' => $sys,
                'wm'     => $wm,
            ],
            'totals' => [
                'clicks' => (int) ($tot->clicks_valid ?? 0),
                'spend'  => (float) ($tot->spend_total ?? 0.0),
                'system' => max(0.0, (float) (($tot->spend_total ?? 0.0) - ($tot->wm_total ?? 0.0))),
                'wm'     => (float) ($tot->wm_total ?? 0.0),
            ],
            'group'  => $group,
            'period' => [$from, $to],
            'commission' => $commission,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /** GET /admin/revenue/csv — CSV выгрузка выручки */
    public function revenueCsv(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);

        // Грануляция: day|month|year
        $group = $request->string('group', 'day')->toString();
        $format = match ($group) {
            'month' => '%Y-%m-01',
            'year'  => '%Y-01-01',
            default => '%Y-%m-%d',
        };

        $commission = (float) config('sf.commission', 0.20);

        $base = DB::table('clicks as c')
            ->leftJoin('subscriptions as s', 's.id', '=', 'c.subscription_id')
            ->leftJoin('offers as o', 'o.id', '=', 's.offer_id')
            ->whereBetween(DB::raw('COALESCE(c.clicked_at, c.created_at)'), [$from, $to])
            ->where('c.is_valid', 1);

        $rows = (clone $base)
            ->selectRaw("DATE_FORMAT(COALESCE(c.clicked_at, c.created_at), ?) as g", [$format])
            ->selectRaw("COUNT(*) AS clicks_valid")
            ->selectRaw("SUM(COALESCE(c.adv_cost, o.cpc)) AS spend_total")
            ->selectRaw("SUM(COALESCE(c.wm_payout, COALESCE(c.adv_cost, o.cpc) * (1 - ?))) AS wm_total", [$commission])
            ->groupBy('g')
            ->orderBy('g')
            ->get();

        $delimiter = (string) config('sf.csv.delimiter', ';');
        $filename  = 'admin_revenue_' . $group . '_' . date('Y-m-d_His') . '.csv';
        $headers   = [
            'Content-Type'        => 'text/csv; charset=Windows-1251',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ];

        return response()->stream(function () use ($rows, $group, $delimiter) {
            echo "sep={$delimiter}\r\n";
            $out = fopen('php://output', 'w');

            $write = function (array $fields) use ($out, $delimiter) {
                $encoded = array_map(fn($v) => mb_convert_encoding((string)$v, 'Windows-1251', 'UTF-8'), $fields);
                fputcsv($out, $encoded, $delimiter);
            };

            // Заголовок
            $write(['Период (' . $group . ')','Клики (валидные)','Расход рекламодателей, руб.','Выплата WM, руб.','Доход системы, руб.']);

            $totValid = 0;
            $totAdv = 0.0;
            $totWm  = 0.0;
            $totSys = 0.0;

            foreach ($rows as $r) {
                $adv  = (float) $r->spend_total;
                $wm   = (float) $r->wm_total;
                $sys  = max(0.0, $adv - $wm);

                // Красивый период
                $pretty = $r->g;
                try {
                    if ($group === 'day') {
                        $pretty = Carbon::parse($r->g)->translatedFormat('d.m.Y');
                    } elseif ($group === 'month') {
                        $pretty = Carbon::parse($r->g)->translatedFormat('m.Y');
                    } elseif ($group === 'year') {
                        $pretty = Carbon::parse($r->g)->translatedFormat('Y');
                    }
                } catch (\Throwable $e) {
                }

                $write([
                    $pretty,
                    (int) $r->clicks_valid,
                    number_format($adv, 2, ',', ''),
                    number_format($wm, 2, ',', ''),
                    number_format($sys, 2, ',', ''),
                ]);

                $totValid += (int) $r->clicks_valid;
                $totAdv   += $adv;
                $totWm    += $wm;
                $totSys   += $sys;
            }

            // Итого
            $write([
                'Итого',
                (int) $totValid,
                number_format($totAdv, 2, ',', ''),
                number_format($totWm,  2, ',', ''),
                number_format($totSys, 2, ',', ''),
            ]);

            fclose($out);
        }, 200, $headers);
    }

    /** GET /admin/subscriptions — список подписок */
    public function subscriptions(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);

        $q = Subscription::query()
            ->with(['offer:id,name,advertiser_id', 'webmaster:id,name,email'])
            ->whereBetween(DB::raw('COALESCE(created_at, updated_at)'), [$from, $to])
            ->orderByDesc('id');

        if ($s = trim((string)$request->get('q'))) {
            $q->where(function ($w) use ($s) {
                $w->where('token', 'like', "%{$s}%")
                  ->orWhereHas('offer', fn($wo) => $wo->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('webmaster', fn($wm) => $wm
                      ->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%"));
            });
        }
        if (($active = $request->get('active')) !== null) {
            if ($active === '1' || $active === '0') {
                $q->where('is_active', $active === '1');
            }
        }

        $subs = $q->paginate(20)->withQueryString();

        return view('admin.subscriptions', [
            'subs'   => $subs,
            'from'   => $from,
            'to'     => $to,
            'period' => $period,
            'q'      => $request->get('q'),
            'active' => $request->get('active'),
        ]);
    }

    /** GET /admin/subscriptions/csv — CSV выгрузка подписок */
    public function subscriptionsCsv(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);

        $q = Subscription::query()
            ->with(['offer:id,name,advertiser_id', 'webmaster:id,name,email'])
            ->whereBetween(DB::raw('COALESCE(created_at, updated_at)'), [$from, $to])
            ->orderByDesc('id');

        if ($s = trim((string)$request->get('q'))) {
            $q->where(function ($w) use ($s) {
                $w->where('token', 'like', "%{$s}%")
                  ->orWhereHas('offer', fn($wo) => $wo->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('webmaster', fn($wm) => $wm
                      ->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%"));
            });
        }
        if (($active = $request->get('active')) !== null) {
            if ($active === '1' || $active === '0') {
                $q->where('is_active', $active === '1');
            }
        }

        $rows = $q->get();

        $delimiter = (string) config('sf.csv.delimiter', ';');
        $filename  = 'admin_subscriptions_' . date('Y-m-d_His') . '.csv';
        $headers   = [
            'Content-Type'        => 'text/csv; charset=Windows-1251',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ];

        return response()->stream(function () use ($rows, $delimiter) {
            echo "sep={$delimiter}\r\n";
            $out = fopen('php://output', 'w');

            $write = function (array $fields) use ($out, $delimiter) {
                $encoded = array_map(fn($v) => mb_convert_encoding((string)$v, 'Windows-1251', 'UTF-8'), $fields);
                fputcsv($out, $encoded, $delimiter);
            };

            $write(['ID','Токен','Активна','Оффер','Вебмастер','Email','Создано','Обновлено']);

            foreach ($rows as $r) {
                $flag = (bool) $r->is_active;
                $write([
                    (int) $r->id,
                    (string) $r->token,
                    $flag ? 'Да' : 'Нет',
                    $r->offer->name ?? '',
                    $r->webmaster->name ?? '',
                    $r->webmaster->email ?? '',
                    optional($r->created_at)->format('d.m.Y H:i'),
                    optional($r->updated_at)->format('d.m.Y H:i'),
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }
}
