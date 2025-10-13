<?php

namespace App\Jobs;

use App\Models\Click;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogClickJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $token,
        public string $ip,
        public ?string $ua,
        public \DateTimeInterface $ts,
        public ?string $ref = null
    ) {}

    public function handle(): void
    {
        // 1) Ищем активную подписку по токену
        $sub = Subscription::query()
            ->where('token', $this->token)
            ->where('is_active', true)
            ->first();

        if (!$sub) {
            \App\Models\Click::create([
                'subscription_id' => null,
                'token'           => $this->token,
                'ip'              => $this->ip,
                'user_agent'      => $this->ua,
                'referrer'        => $this->ref,
                'clicked_at'      => $this->ts,   // у вас в Job уже есть timestamp
                'is_valid'        => 0,
                'invalid_reason'  => 'not_subscribed',
            ]);
            return;
        }

        // 2) Дедупликация: тот же IP+UA+subscription в окне 30 сек — пропускаем
        $exists = Click::query()
            ->where('subscription_id', $sub->id)
            ->where('ip', $this->ip)
            ->where('user_agent', $this->ua)
            // БЫЛО: ->where('clicked_at', '>=', now()->subSeconds(30))
            // СТАЛО: окно относительно времени клика, а не времени обработки
            ->whereBetween('clicked_at', [
                \Illuminate\Support\Carbon::instance($this->ts)->subSeconds(30),
                \Illuminate\Support\Carbon::instance($this->ts)->addSeconds(30),
            ])
            ->exists();

        if ($exists) return;



        // 3) Простейший бот-фильтр по UA (минимум — расширим позже)
        $ua = mb_strtolower($this->ua ?? '');
        $maybeBot = $ua === '' || str_contains($ua, 'bot') || str_contains($ua, 'crawler') || str_contains($ua, 'spider');

        // 4) Запись клика
        Click::create([
            'subscription_id' => $sub->id,
            'clicked_at'      => $this->ts,
            'ip'              => $this->ip,
            'user_agent'      => $this->ua,
            'referrer'        => $this->ref,
            'is_valid'        => $maybeBot ? 0 : 1,
            'token'           => $this->token,
            // 'redirected_at' — не трогаем; это поле у вас ставилось синхронно, но его можно не использовать
        ]);
    }
}
