<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    // Активные офферы
    public function offers()
    {
        $offers = Offer::where('is_active', true)->latest()->paginate(10);
        return view('wm.offers', compact('offers'));
    }

    // Подписаться на оффер (WM указывает свою ставку)
    public function subscribe(Request $request, Offer $offer)
    {
        if (!$offer->is_active) {
            abort(404);
        }

        // 1) Валидируем "Мою стоимость клика" (в рублях)
        $data = $request->validate([
            'cpc' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ], [], ['cpc' => 'Моя стоимость клика']);

        // 2) Ищем/создаём подписку по связке WM×Offer
        $sub = Subscription::firstOrNew([
            'offer_id'     => $offer->id,
            'webmaster_id' => $request->user()->id,
        ]);

        // 3) Обновляем ставку и активируем подписку
        $sub->cpc       = $data['cpc'];                 // Моя стоимость клика (ставка WM)
        $sub->is_active = true;
        $sub->token     = $sub->token ?: Str::ulid();   // генерим токен, если не было
        $sub->save();

        return redirect()
            ->route('wm.subs.index')
            ->with('status', 'Подписка оформлена. Ваша стоимость клика: ' . number_format($sub->cpc, 2, ',', ' ') . ' ₽.');
    }

    // Мои подписки
    public function index(Request $request)
    {
        $subs = Subscription::with('offer')
            ->where('webmaster_id', $request->user()->id)
            ->latest()->paginate(10);

        return view('wm.subs', compact('subs'));
    }

    // Отписаться (деактивация подписки)
    public function unsubscribe(Request $request, int $subscriptionId)
    {
        $sub = Subscription::where('id', $subscriptionId)
            ->where('webmaster_id', $request->user()->id)
            ->firstOrFail();

        $sub->is_active = false;
        $sub->save();

        return back()->with('status', 'Вы отписались от оффера. Подписка деактивирована.');
    }
}
