<?php

namespace App\Http\Controllers;

use App\Jobs\LogClickJob;
use App\Models\Subscription;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    /*
     Публичный редиректор: /r/{token}
     Теперь: редирект мгновенно, лог клика — в очередь (быстро и надёжно).
    */
    public function __invoke(Request $request, string $token)
    {
        // 1) Асинхронно отправляем запись клика в очередь
        LogClickJob::dispatch(
            token: $token,
            ip: (string) $request->ip(),
            ua: (string) $request->userAgent(),
            ts: now(),
            ref: $request->headers->get('referer')
        );

        // 2) Готовим целевой URL ТАК ЖЕ, как было (с протаскиванием исходной query-строки)
        $sub = Subscription::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        // Если подписки нет — 404 (как и прежде)
        if (!$sub || empty($sub->offer?->target_url)) {
            abort(404);
        }

        $target = $sub->offer->target_url;
        $query  = $request->getQueryString();

        $final  = $query
            ? $target . (str_contains($target, '?') ? '&' : '?') . $query
            : $target;

        // 3) Мгновенный 302 на целевой URL
        return redirect()->away($final, 302);
    }
}
