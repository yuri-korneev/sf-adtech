<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\Wm\SubscriptionController;
use App\Http\Controllers\Adv\OfferController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Wm\StatsController as WmStatsController;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

// Главная / дэшборд
Route::get('/', fn () => view('welcome'));
Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Профиль
Route::middleware('auth')->group(function () {
    Route::get('/profile',  [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',[ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',[ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Пробные маршруты
Route::middleware(['auth','role:webmaster'])->get('/webmaster', fn () => 'Webmaster OK');
Route::middleware(['auth','role:advertiser'])->get('/advertiser', fn () => 'Advertiser OK');
Route::middleware(['auth','role:admin'])->get('/_middleware_check', fn () => 'hello admin');

// B3: редиректор /r/{token} с лимитом
RateLimiter::for('redirects', function (Request $request) {
    return [ Limit::perMinute(600)->by($request->ip()) ];
});
Route::middleware('throttle:redirects')->get('/r/{token}', RedirectController::class)->name('redirect');

// B4: группа для веб-мастера
Route::middleware(['auth','role:webmaster'])
    ->prefix('wm')
    ->name('wm.')
    ->group(function () {
        Route::get('/', fn () => view('wm.home'))->name('home');

        Route::get('/offers', [SubscriptionController::class, 'offers'])->name('offers');
        Route::post('/subscribe/{offer}', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subs.index');

        // Статистика вебмастера (БЕЗ вложенной wm-группы!)
        Route::get('/stats',      [WmStatsController::class, 'stats'])->name('stats');
        Route::get('/stats/data', [WmStatsController::class, 'statsData'])->name('stats.data');
        Route::get('/stats/csv',  [WmStatsController::class, 'statsCsv'])->name('stats.csv');

        // Отписаться — ВАЖНО: без повторного "wm." в name()
        Route::post('/subscriptions/{id}/unsubscribe', [SubscriptionController::class, 'unsubscribe'])
            ->name('unsubscribe');
});

// B4: группа для рекламодателя
Route::middleware(['auth','role:advertiser'])->prefix('adv')->name('adv.')->group(function () {
    Route::get('/', fn () => view('adv.home'))->name('home');

    Route::get('/offers/stats', [\App\Http\Controllers\Adv\OfferController::class, 'offersStats'])->name('offers.stats');
    Route::get('/offers/{offer}/subscriptions', [\App\Http\Controllers\Adv\OfferController::class, 'subscriptions'])->name('offers.subscriptions');

    Route::resource('offers', \App\Http\Controllers\Adv\OfferController::class)->whereNumber('offer');

    Route::get('/stats', [\App\Http\Controllers\Adv\OfferController::class, 'stats'])->name('stats');
    Route::get('/stats/csv', [OfferController::class, 'statsCsv'])->name('stats.csv');
});

// B5: группа для админа
Route::middleware(['auth','role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                       [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users',                  [AdminController::class, 'users'])->name('users');
    Route::post('/users/{user}/toggle',   [AdminController::class, 'toggleUser'])->name('users.toggle');

    Route::get('/offers',                 [AdminController::class, 'offers'])->name('offers');
    Route::post('/offers/{offer}/toggle', [AdminController::class, 'toggleOffer'])->name('offers.toggle');

    Route::get('/clicks',                 [AdminController::class, 'clicks'])->name('clicks');
    Route::get('/clicks/stats',           [AdminController::class, 'clicksStats'])->name('clicks.stats');
    Route::get('/clicks/csv',             [AdminController::class, 'clicksCsv'])->name('clicks.csv');

    // Темы
    Route::resource('topics', \App\Http\Controllers\Admin\TopicController::class)
        ->parameters(['topics' => 'topic'])
        ->names('topics');

    // dev: генератор кликов
    /*Route::post('/dev/generate-clicks-simple', function (\Illuminate\Http\Request $request) {
        abort_unless(app()->environment('local') || config('app.debug'), 403, 'Dev only');
        $count = (int) $request->input('count', 40);
        $days  = max(1, (int) $request->input('days', 14));
        for ($i = 0; $i < $count; $i++) {
            $isValid = (bool) random_int(0, 1);
            \DB::table('clicks')->insert([
                'subscription_id' => null,
                'token'           => \Illuminate\Support\Str::random(16),
                'is_valid'        => $isValid,
                'invalid_reason'  => $isValid ? null : (random_int(0, 3) === 0 ? 'not_subscribed' : null),
                'clicked_at'      => now()->subDays(random_int(0, max(1, $days - 1)))->setTime(random_int(0, 23), random_int(0, 59)),
                'ip'              => '127.0.0.1',
                'user_agent'      => 'Mozilla/5.0 (Test)',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
        return back()->with('status', "Добавлено {$count} кликов за {$days} дней.");
    })->name('dev.generateClicksSimple'); */

});

require __DIR__.'/auth.php';
