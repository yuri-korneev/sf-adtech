<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Click;

class GenerateClicks extends Command
{
    protected $signature = 'clicks:generate
        {count=100 : Сколько кликов создать}
        {--days=14 : За сколько последних дней раскидать}
        {--refused=25 : Процент отказов (из невалидных), 0..100}
    ';

    protected $description = 'Сгенерировать тестовые клики (валид/невалид/отказы) без хатрагивания существующих записей';

    public function handle(): int
    {
        $count   = (int) $this->argument('count');
        $days    = max(1, (int) $this->option('days'));
        $refused = min(100, max(0, (int) $this->option('refused')));

        $this->info("Генерируем {$count} кликов за последние {$days} дни, отказов ~{$refused}% от невалидных...");

        // убедимся, что у модели включены фабрики
        if (! method_exists(Click::class, 'factory')) {
            $this->error('У модели Click нет фабрики. Создай database/factories/ClickFactory.php.');
            return self::FAILURE;
        }

        Click::factory()
            ->count($count)
            ->state(function () use ($days, $refused) {
                $isValid = (bool) random_int(0, 1);
                $invalidReason = null;
                if (! $isValid) {
                    // доля отказов среди невалидных
                    if (random_int(1, 100) <= $refused) {
                        $invalidReason = 'not_subscribed';
                    }
                }
                $ts = now()
                    ->subDays(random_int(0, max(1, $days - 1)))
                    ->setTime(random_int(0, 23), random_int(0, 59));

                return [
                    'is_valid'       => $isValid,
                    'invalid_reason' => $invalidReason,
                    'clicked_at'     => $ts,
                ];
            })
            ->create();

        $this->info('Готово.');
        return self::SUCCESS;
    }
}
