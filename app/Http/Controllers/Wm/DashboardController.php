<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Click;
use App\Models\Subscription;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user(); // веб-мастер
        $from = now()->subDays(30)->startOfDay();
        $to   = now()->endOfDay();

        $clicksBase = Click::whereBetween(
            DB::raw('COALESCE(clicks.clicked_at, clicks.created_at)'),
            [$from, $to]
        )
            ->whereHas('subscription', fn($q) => $q->where('webmaster_id', $user->id));

        $clicksAll   = (clone $clicksBase)->count();
        $clicksValid = (clone $clicksBase)->where('is_valid', 1)->count();

        // Выплата WM = сумма(cpc * 0.8) по валидным кликам своих подписок
        $payout = Click::join('subscriptions', 'subscriptions.id', '=', 'clicks.subscription_id')
            ->join('offers', 'offers.id', '=', 'subscriptions.offer_id')
            ->where('subscriptions.webmaster_id', $user->id)
            ->whereBetween(DB::raw('COALESCE(clicks.clicked_at, clicks.created_at)'), [$from, $to])
            ->where('clicks.is_valid', 1)
            ->value(DB::raw('COALESCE(SUM(offers.cpc * 0.8), 0)'));

        $subsActive = Subscription::where('webmaster_id', $user->id)
            ->where('is_active', 1)
            ->count();

        $lastClicks = (clone $clicksBase)
            ->with(['subscription.offer'])
            ->orderByDesc('clicks.id')
            ->paginate(10);

        $stats = [
            'clicks_all'   => $clicksAll,
            'clicks_valid' => $clicksValid,
            'payout_rub'   => round((float)$payout, 2),
            'subs_active'  => $subsActive,
        ];

        return view('wm.dashboard', compact('stats', 'lastClicks'));
    }
}
