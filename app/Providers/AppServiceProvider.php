<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // На всякий случай для старых MySQL/utf8mb4
        Schema::defaultStringLength(191);

        // Локаль приложения (совместимо с laravel-lang/*)
        App::setLocale('ru');

        // Локаль для дат (Carbon/Date::translatedFormat и пр.)
        Carbon::setLocale('ru');
        Date::setLocale('ru');

        // Если нужно — часовой пояс проекта
        // (используй тот, который вам нужен по ТЗ: Europe/Sofia или Europe/Moscow)
        config(['app.timezone' => 'Europe/Sofia']);
        date_default_timezone_set(config('app.timezone', 'UTC'));
    }
}
