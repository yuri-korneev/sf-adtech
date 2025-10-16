<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class ClickSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Соберём все существующие подписки (id, для связки клика)
        $subIds = DB::table('subscriptions')->pluck('id')->all();

        // Если подписок нет — на этом этапе лучше не создавать «левые» клики.
        if (empty($subIds)) {
            $this->command?->warn('Нет подписок: пропускаю генерацию кликов (некому их привязать).');
            return;
        }

        $rows = [];
        $from = now()->subDays(13)->startOfDay(); // последние 14 дней
        $to   = now()->endOfDay();
        $day  = $from->copy();

        while ($day->lte($to)) {
            $perDay = random_int(10, 25); // 10..25 кликов в день
            for ($i = 0; $i < $perDay; $i++) {
                $isValid = (bool) random_int(0, 1);
                $rows[] = [
                    'subscription_id' => Arr::random($subIds),               
                    'token'           => Str::random(16),
                    'is_valid'        => $isValid,
                    'invalid_reason'  => $isValid ? null : (random_int(0, 3) === 0 ? 'not_subscribed' : null),
                    'clicked_at'      => $day->copy()->setTime(random_int(0,23), random_int(0,59), random_int(0,59)),
                    'ip'              => '127.0.0.1',
                    'user_agent'      => 'Mozilla/5.0 (Seeder)',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }

            if (count($rows) >= 1000) {
                DB::table('clicks')->insert($rows);
                $rows = [];
            }

            $day->addDay();
        }

        if ($rows) {
            DB::table('clicks')->insert($rows);
        }
    }
}
