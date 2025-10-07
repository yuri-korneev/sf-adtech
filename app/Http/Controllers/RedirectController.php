<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Subscription;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    /*
     Публичный редиректор: /r/{token}
     Логируем клик -> проверяем подписку/оффер -> 302 (валид) или 404 (невалид).
     */
    public function __invoke(Request $request, string $token)
    {
        // 1) Логируем сам факт клика (даже если токен невалидный)
        $click = new Click([
            'token'      => $token,
            'ip'         => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 512),
            'referrer'   => substr((string)$request->headers->get('referer'), 0, 1024),
            'clicked_at' => now(),
        ]);

        // 2) Ищем активную подписку и проверяем, что оффер активен
        $sub = Subscription::with('offer')
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if (!$sub || !$sub->offer || !$sub->offer->is_active) {
            // Отказ: фиксируем причину и отдаём 404
            $click->is_valid = false;
            $click->invalid_reason = $sub ? 'offer_inactive' : 'token_not_found';
            $click->save();

            abort(404);
        }

        // 3) Валидный клик → связываем, фиксируем редирект, сохраняем
        $click->subscription()->associate($sub);
        $click->is_valid = true;
        $click->redirected_at = now();
        $click->save();

        // 4) 302 на целевой URL с протаскиванием query-строки (?utm=..., sub_id=...)
        // если target_url уже содержит '?', добавим '&', иначе — '?'
        $target = $sub->offer->target_url;
        $query  = $request->getQueryString();
        $final  = $query
            ? $target . (str_contains($target, '?') ? '&' : '?') . $query
            : $target;

        return redirect()->away($final, 302);
    }
}
