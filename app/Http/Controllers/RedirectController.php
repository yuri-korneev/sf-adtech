<?php

namespace App\Http\Controllers;

use App\Jobs\LogClickJob;
use App\Jobs\MarkRedirectedJob;
use App\Models\Click;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RedirectController extends Controller
{
    /**
     * Публичный редиректор: /r/{token}
     *
     * Требования ТЗ:
     * - 404 если подписка/оффер невалидны или нет target_url.
     * - Лог клика — асинхронно (LogClickJob), ошибка джобы не должна ломать ответ.
     * - Пометка redirected_at — сразу (best effort) + отложенной джобой.
     * - 302 на целевой URL с прокидыванием query-строки.
     */
    public function __invoke(Request $request, string $token)
    {
        // 1) ВАЛИДАЦИЯ ПОДПИСКИ/ОФФЕРА (строго до любой побочной логики)
        $sub = Subscription::query()
            ->with('offer')
            ->where('token', $token)
            ->first();

        $isSubActive   = (bool) ($sub?->is_active ?? false);
        $offer         = $sub?->offer;
        $isOfferActive = (bool) ($offer?->is_active ?? false);
        $targetUrl     = (string) ($offer?->target_url ?? '');

        if (!$sub || !$isSubActive || !$offer || !$isOfferActive || $targetUrl === '') {
            abort(404);
        }

        // 2) СБОР ФИНАЛЬНОГО URL с сохранением исходной query-строки
        $query = (string) $request->getQueryString();
        $final = $query
            ? $targetUrl . (str_contains($targetUrl, '?') ? '&' : '?') . $query
            : $targetUrl;

        // 3) ЛОГ КЛИКА — "best effort" (асинхронно; сбой логирования не ломает редирект)
        $ip       = (string) $request->ip();
        $ua       = (string) $request->userAgent();
        $referrer = (string) ($request->headers->get('referer') ?? '');
        try {
            LogClickJob::dispatch($token, $ip, $ua, $referrer)
                ->onQueue('clicks'); // при желании можно убрать/переименовать очередь
        } catch (\Throwable $e) {
            report($e); // не ломаём пользовательский поток
        }

        // 4) ИДЕМПОТЕНТНАЯ ПОМЕТКА redirected_at (оптимистично)
        //    Окно "свежести" клика берём из конфига, иначе 10 минут.
        $now    = Carbon::now();
        $window = (int) (config('sf.dedup_window_seconds') ?? 600); // 600s = 10 минут

        try {
            // Помечаем один "свежий" клик как редиректнутый
            $updated = Click::query()
                ->where('token', $token)
                ->where('subscription_id', $sub->id)
                ->whereNull('redirected_at')
                ->whereBetween('clicked_at', [$now->copy()->subSeconds($window), $now->copy()->addMinute()])
                ->orderByDesc('id')
                ->limit(1)
                ->update(['redirected_at' => $now]);

            // Если свежей записи не нашли, и вообще за окно ее нет — создадим минимальную запись сразу,
            // чтобы отчёты D/M/Y были консистентны (остальное досчитают джобы/отчёты).
            if ($updated === 0) {
                $existsFresh = Click::query()
                    ->where('token', $token)
                    ->where('subscription_id', $sub->id)
                    ->whereBetween('clicked_at', [$now->copy()->subSeconds($window), $now->copy()->addMinute()])
                    ->exists();

                if (!$existsFresh) {
                    Click::create([
                        'token'           => $token,
                        'subscription_id' => $sub->id,
                        'offer_id'        => $sub->offer_id,
                        'webmaster_id'    => $sub->webmaster_id,
                        'advertiser_id'   => $offer->advertiser_id,

                        'ip'              => $ip,
                        'user_agent'      => mb_substr($ua, 0, 512),
                        'referer'         => mb_substr($referrer, 0, 1024),
                        'referrer'        => mb_substr($referrer, 0, 1024),

                        'clicked_at'      => $now,
                        'redirected_at'   => $now,

                        'is_valid'        => 1,
                        'invalid_reason'  => null,

                        // Денежные поля — если БД допускает null; точный расчёт может делать джоба
                        // 'adv_cost'     => $offer->cpc ?? null,
                        // 'wm_payout'    => $sub->rate ?? null,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Логируем, но редирект не ломаем
            report($e);
        }

        // 5) Отложенная пометка через джобу (на случай гонок)
        try {
            MarkRedirectedJob::dispatch(
                token: $token,
                ts: CarbonImmutable::now()
            )->delay(now()->addSeconds(2));
        } catch (\Throwable $e) {
            report($e);
        }

        // 6) Немедленный редирект (строго по ТЗ)
        return redirect()->away($final, 302);
    }
}
