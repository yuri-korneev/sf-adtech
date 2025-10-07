<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Click;
use App\Models\Offer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Topic;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Cache;


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
                    } catch (\Throwable $e) { /* оставим дефолт */ }
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
                'admin'      => User::where('role','admin')->count(),
                'advertiser' => User::where('role','advertiser')->count(),
                'webmaster'  => User::where('role','webmaster')->count(),
                'active'     => User::where('is_active', true)->count(),
            ],
            'offers' => [
                'total'  => Offer::count(),
                'active' => Offer::where('is_active', true)->count(),
            ],
            'subs' => Subscription::count(),
            'clicks' => [
                'total' => Click::whereBetween('clicked_at', [$from, $to])->count(),
                'valid' => Click::whereBetween('clicked_at', [$from, $to])->where('is_valid', true)->count(),
            ],
            'range' => [$from, $to, $period],
        ];

        $latestClicks = Click::with(['subscription.offer:id,name', 'subscription.webmaster:id,name'])
            ->whereBetween('clicked_at', [$from, $to])
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
            $q->where(function($w) use ($search) {
                $w->where('email','like',"%{$search}%")
                  ->orWhere('name','like',"%{$search}%");
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
        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('status', 'Состояние пользователя обновлено');
    }

    /** GET /admin/offers — список офферов */
   public function offers(Request $request)
    {
        $q       = Offer::query()
            ->with(['advertiser:id,name,email', 'topics:id,name'])
            ->withCount([
                'subscriptions',
                'clicks as clicks_count',
                'clicks as valid_clicks_count' => fn($w) => $w->where('is_valid', true),
            ])
            ->orderByDesc('id');

        // Поиск по названию/URL
        if ($search = trim((string)$request->get('q'))) {
            $q->where(function($w) use ($search) {
                $w->where('name','like',"%{$search}%")
                ->orWhere('target_url','like',"%{$search}%");
            });
        }

        // Фильтр по активности
        if (null !== ($active = $request->get('active'))) {
            if ($active === '1' || $active === '0') {
                $q->where('is_active', $active === '1');
            }
        }

        // ★ Фильтр по теме: /admin/offers?topic=<id>
        if ($topicId = $request->get('topic')) {
            $q->whereHas('topics', fn($w) => $w->where('topics.id', $topicId));
        }

        $offers = $q->paginate(15)->withQueryString();

        // Справочник тем для возможного дропдауна на странице офферов
        $topics  = Topic::orderBy('name')->get(['id','name']);

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
        $offer->is_active = ! $offer->is_active;
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

        if ($token = trim((string)$request->get('q'))) {
            $q->where('token','like',"%{$token}%");
        }
        if (($valid = $request->get('valid')) !== null) {
            if ($valid === '1') $q->where('is_valid', true);
            if ($valid === '0') $q->where('is_valid', false);
        }

        $clicks = $q->paginate(20)->withQueryString();
        return view('admin.clicks', compact('clicks', 'period', 'from', 'to'));
    }

   public function clicksStats(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);
        $valid = $request->string('valid')->value();

        $base = DB::table('clicks')
            ->whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to]);

        if ($valid === '1')      $base->where('is_valid', 1);
        elseif ($valid === '0')  $base->where('is_valid', 0);

        $rows = (clone $base)
            ->selectRaw("
                DATE(COALESCE(clicked_at, created_at)) AS d,
                SUM(is_valid = 1)                                       AS valid_cnt,
                SUM(is_valid = 0)                                       AS invalid_cnt,
                SUM(is_valid = 0 AND invalid_reason = 'not_subscribed') AS refused_cnt
            ")->groupBy('d')->orderBy('d')->get();

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
            SUM(is_valid = 0 AND invalid_reason = 'not_subscribed') AS refused
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
        ])->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0');
    }

    public function clicksCsv(Request $request)
    {
        [$from, $to, $period] = $this->dateRange($request);

        $q = Click::query()
            ->with(['subscription.offer:id,name', 'subscription.webmaster:id,name'])
            ->whereBetween(DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to])
            ->orderByDesc(DB::raw('COALESCE(clicked_at, created_at)'));

        if ($token = trim((string)$request->get('q'))) {
            $q->where('token','like',"%{$token}%");
        }
        if (($valid = $request->get('valid')) !== null) {
            if ($valid === '1') $q->where('is_valid', true);
            if ($valid === '0') $q->where('is_valid', false);
        }

        $clicks = $q->get();

        $filename = 'admin_clicks_' . date('Y-m-d_His') . '.csv';
        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($clicks) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','Дата','Token','Валидный','Оффер','Вебмастер']);
            foreach ($clicks as $c) {
                fputcsv($out, [
                    $c->id,
                    optional($c->clicked_at ?? $c->created_at)->format('Y-m-d H:i:s'),
                    $c->token,
                    $c->is_valid ? 'Да' : 'Нет',
                    $c->subscription->offer->name ?? '',
                    $c->subscription->webmaster->name ?? '',
                ]);
            }
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }


}
