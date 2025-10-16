<?php

namespace App\Jobs;

use App\Models\Click;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Логирование клика по URL /r/{token}.
 *
 * ВАЖНО:
 * - Не объявляем $queue в классе (чтобы не конфликтовать с трейтами Laravel).
 * - Очередь можно задать при диспатче: LogClickJob::dispatch(...)->onQueue('clicks');
 * - Пишем в существующие колонки: referer и referrer (обе — для обратной совместимости).
 * - Безопасно обрезаем длинные строки, защищаемся от null.
 */
class LogClickJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Входные данные для логирования */
    public string $token;
    public string $ip;
    public string $ua;
    public ?string $ref;
    public CarbonImmutable $ts;

    /** Параметры очереди/повторов */
    public int $tries = 3;
    public int $timeout = 10;
    /** @var int[]|int */
    public $backoff = [1, 2, 5, 10, 30];

    /**
     * @param string                 $token Токен подписки (subscriptions.token)
     * @param string                 $ip    IP источника
     * @param string                 $ua    User-Agent
     * @param string|null            $ref   HTTP Referer
     * @param CarbonImmutable|null   $ts    Момент клика (clicked_at)
     */
    public function __construct(string $token, string $ip, string $ua, ?string $ref = null, ?CarbonImmutable $ts = null)
    {
        $this->token = $token;
        $this->ip    = $ip;
        $this->ua    = $ua;
        $this->ref   = $ref;
        $this->ts    = $ts ?: CarbonImmutable::now();

        // Если хочешь — сразу направляй эту джобу в нужную очередь:
        // $this->onQueue('clicks');
    }

    public function handle(): void
    {
        // Находим подписку по токену и подгружаем связанный оффер
        $sub = Subscription::query()
            ->with('offer')
            ->where('token', $this->token)
            ->first();

        $isActiveSub   = (bool) ($sub?->is_active ?? false);
        $offer         = $sub?->offer;
        $isActiveOffer = (bool) ($offer?->is_active ?? false);

        $isValid = $sub && $isActiveSub && $offer && $isActiveOffer;

        // Безопасно нормализуем поля окружения
        $ip  = (string) $this->ip;
        $ua  = mb_substr((string) $this->ua, 0, 512);

        // Берём реферер: из переданного в конструктор или из текущего запроса (если job запускают синхронно)
        $ref = (string) ($this->ref
            ?? request()->headers->get('referer')
            ?? request()->server('HTTP_REFERER')
            ?? ''
        );
        $ref = mb_substr($ref, 0, 1024);

        // Подготовим идентификаторы (учитывая возможный null)
        $subscriptionId = $sub?->id;
        $offerId        = $sub?->offer_id ?? $offer?->id;
        $webmasterId    = $sub?->webmaster_id;
        $advertiserId   = $offer?->advertiser_id;

        // Денежные поля — если бизнес-логика уже определена (например, adv_cost = offer->cpc, wm_payout = sub->rate),
        // можно раскомментировать. Если нет — оставляем null (колонки в БД у тебя есть и допускают null).
        $advCost  = $isValid && isset($offer?->cpc) ? $offer->cpc : null;
        $wmPayout = $isValid && isset($sub?->rate)  ? $sub->rate  : null;

        // Записываем клик
        Click::create([
            'token'           => $this->token,
            'subscription_id' => $subscriptionId,
            'offer_id'        => $offerId,
            'webmaster_id'    => $webmasterId,
            'advertiser_id'   => $advertiserId,

            'ip'              => $ip,
            'user_agent'      => $ua,

            // ВАЖНО: используем существующие в твоей таблице колонки
            'referer'         => $ref,
            'referrer'        => $ref,

            'clicked_at'      => $this->ts,       // CarbonImmutable → сохранится как datetime
            // redirected_at — не трогаем здесь; помечается позднее отдельной логикой (если есть)

            'is_valid'        => $isValid ? 1 : 0,
            'invalid_reason'  => $isValid
                ? null
                : ($sub
                    ? ($isActiveSub ? ($isActiveOffer ? null : 'offer_inactive') : 'inactive')
                    : 'not_subscribed'),

            // Денежные поля — по необходимости; null допустим
            'adv_cost'        => $advCost,
            'wm_payout'       => $wmPayout,
        ]);
    }
}
