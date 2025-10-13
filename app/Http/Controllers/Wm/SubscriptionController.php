<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * Список доступных активных офферов для подписки (WM).
     */
    public function offers()
    {
        $offers = Offer::query()
            ->where('is_active', true)
            ->latest()
            ->paginate(10);

        return view('wm.offers', compact('offers'));
    }

    /**
     * Подписка на оффер.
     * ВАЖНО: по ТЗ CPC задаёт рекламодатель (offers.cpc).
     * Веб-мастер НЕ указывает свою цену — мы её не читаем и не сохраняем.
     */
    public function subscribe(Request $request, Offer $offer)
    {
        // оффер должен быть активен
        if (!$offer->is_active) {
            abort(404);
        }

        // создаём/находим подписку WM×Offer
        $sub = Subscription::firstOrNew([
            'offer_id'     => $offer->id,
            'webmaster_id' => $request->user()->id,
        ]);

        // активируем подписку и гарантируем наличие токена
        $sub->is_active = true;
        $sub->token     = $sub->token ?: Str::ulid();
        // ВАЖНО: НЕ трогаем/НЕ заполняем $sub->cpc — ставка берётся из offers.cpc
        $sub->save();

        // Для наглядности можем подсказать экономику выплат WM
        $commission  = (float) config('sf.commission', 0.20);
        $payoutPerClick = $offer->cpc * (1 - $commission);

        return redirect()
            ->route('wm.subs.index')
            ->with(
                'status',
                'Подписка оформлена. Ставка оффера: ' .
                number_format((float) $offer->cpc, 2, ',', ' ') . ' ₽. ' .
                'Ваша выплата за валидный клик: ' .
                number_format((float) $payoutPerClick, 2, ',', ' ') . ' ₽.'
            );
    }

    /**
     * Мои подписки (WM).
     */
    public function index(Request $request)
    {
        $subs = Subscription::with(['offer'])
            ->where('webmaster_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('wm.subs', compact('subs'));
    }

    /**
     * Отписка (деактивация подписки).
     */
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
