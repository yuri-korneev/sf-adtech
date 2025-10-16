<?php

namespace App\Http\Controllers\Adv;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Click;
use App\Models\Subscription;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user(); // рекламодатель
        $from = now()->subDays(30)->startOfDay();
        $to   = now()->endOfDay();

        $clicksBase = Click::whereBetween(
            DB::raw('COALESCE(clicks.clicked_at, clicks.created_at)'),
            [$from, $to]
        )
            ->whereHas('subscription.offer', fn($q) => $q->where('advertiser_id', $user->id));

        $clicksAll   = (clone $clicksBase)->count();
        $clicksValid = (clone $clicksBase)->where('is_valid', 1)->count();

        // Расход рекламодателя = сумма(cpc) по валидным кликам его офферов
        $cost = Click::join('subscriptions', 'subscriptions.id', '=', 'clicks.subscription_id')
            ->join('offers', 'offers.id', '=', 'subscriptions.offer_id')
            ->whereBetween(DB::raw('COALESCE(clicks.clicked_at, clicks.created_at)'), [$from, $to])
            ->where('clicks.is_valid', 1)
            ->where('offers.advertiser_id', $user->id)
            ->value(DB::raw('COALESCE(SUM(offers.cpc), 0)'));

        $subsActive = Subscription::where('is_active', 1)
            ->whereHas('offer', fn($q) => $q->where('advertiser_id', $user->id))
            ->count();

        $lastClicks = (clone $clicksBase)
            ->with(['subscription.offer', 'subscription.webmaster'])
            ->orderByDesc('clicks.id')
            ->paginate(10);

        $stats = [
            'clicks_all'   => $clicksAll,
            'clicks_valid' => $clicksValid,
            'cost_rub'     => round((float)$cost, 2),
            'subs_active'  => $subsActive,
        ];

        return view('adv.dashboard', compact('stats', 'lastClicks'));
    }
}
