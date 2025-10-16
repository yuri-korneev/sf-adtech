<?php

namespace App\Jobs;

use App\Models\Click;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Идемпотентная пометка redirected_at для клика по token.
 * Алгоритм:
 * 1) Пытаемся пометить "свежий" клик в окне dedup.
 * 2) Если не нашли (воркер отработал поздно) — помечаем просто самый новый неотмеченный клик по token.
 */
class MarkRedirectedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $token;
    public CarbonImmutable $ts;

    public int $tries = 3;
    public int $timeout = 10;
    /** @var int[]|int */
    public $backoff = [1, 2, 5, 10, 30];

    public function __construct(string $token, ?CarbonImmutable $ts = null)
    {
        $this->token = $token;
        $this->ts    = $ts ?: CarbonImmutable::now();
        // по желанию: $this->onQueue('clicks');
    }

    public function handle(): void
    {
        try {
            $now    = $this->ts->toCarbon();
            $window = (int) (config('sf.dedup_window_seconds') ?? 600); // 10 мин по умолчанию

            // 1) Пробуем пометить "свежий" клик
            $updated = Click::query()
                ->where('token', $this->token)
                ->whereNull('redirected_at')
                ->whereBetween('clicked_at', [$now->copy()->subSeconds($window), $now->copy()->addMinute()])
                ->orderByDesc('id')
                ->limit(1)
                ->update(['redirected_at' => $now]);

            if ($updated === 0) {
                // 2) План Б: пометить просто самый новый неотмеченный клик по токену (надёжность отчётов)
                $latest = Click::query()
                    ->where('token', $this->token)
                    ->whereNull('redirected_at')
                    ->orderByDesc('id')
                    ->limit(1)
                    ->first();

                if ($latest) {
                    $latest->redirected_at = $now;
                    $latest->save();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
