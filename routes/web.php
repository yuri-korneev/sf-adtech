<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\Wm\SubscriptionController;
use App\Http\Controllers\Adv\OfferController as AdvOfferController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Wm\StatsController as WmStatsController;
use App\Http\Controllers\Wm\DashboardController as WmDashboardController;
use App\Http\Controllers\Adv\DashboardController as AdvDashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

/**
 * Главная / дэшборд
 */
Route::get('/', fn () => view('welcome'));
Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

/**
 * Профиль
 */
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/**
 * Пробные маршруты
 */
if (config('app.debug')) {
    Route::middleware(['auth','role:webmaster'])->get('/webmaster', fn () => 'Webmaster OK');
    Route::middleware(['auth','role:advertiser'])->get('/advertiser', fn () => 'Advertiser OK');
    Route::middleware(['auth','role:admin'])->get('/_middleware_check', fn () => 'hello admin');
}

/**
 * Редиректор /r/{token} с лимитом
 */
RateLimiter::for('redirects', function (Request $request) {
    return [ Limit::perMinute(600)->by($request->ip()) ];
});
Route::middleware('throttle:redirects')
    ->get('/r/{token}', RedirectController::class)
    ->name('redirect');

/**
 * Группа для веб-мастера
 */
Route::middleware(['auth','role:webmaster'])
    ->prefix('wm')
    ->name('wm.')
    ->group(function () {
        // главная страница раздела
        Route::get('/', [WmDashboardController::class, 'index'])->name('dashboard');

        Route::get('/offers', [SubscriptionController::class, 'offers'])->name('offers');
        Route::post('/subscribe/{offer}', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subs.index');

        // Статистика вебмастера
        Route::get('/stats', [WmStatsController::class, 'stats'])->name('stats');
        Route::get('/stats/data', [WmStatsController::class, 'statsData'])->name('stats.data');
        Route::get('/stats/csv', [WmStatsController::class, 'statsCsv'])->name('stats.csv');

        // Отписаться
        Route::post('/subscriptions/{id}/unsubscribe', [SubscriptionController::class, 'unsubscribe'])
            ->name('unsubscribe');
    });

/**
 * Группа для рекламодателя
 */
Route::middleware(['auth','role:advertiser'])
    ->prefix('adv')
    ->name('adv.')
    ->group(function () {
        // главная страница раздела
        Route::get('/', [AdvDashboardController::class, 'index'])->name('dashboard');

        Route::get('/offers/stats', [AdvOfferController::class, 'offersStats'])->name('offers.stats');
        Route::get('/offers/{offer}/subscriptions', [AdvOfferController::class, 'subscriptions'])
            ->whereNumber('offer')
            ->name('offers.subscriptions');

        Route::resource('offers', AdvOfferController::class)->whereNumber('offer');

        Route::get('/stats', [AdvOfferController::class, 'stats'])->name('stats');
        Route::get('/stats/csv', [AdvOfferController::class, 'statsCsv'])->name('stats.csv');

        // имя без повторного "adv."
        Route::post('/offers/{offer}/status', [AdvOfferController::class, 'setStatus'])
            ->whereNumber('offer')
            ->name('offers.status');
    });

/**
 * Группа для админа
 */
Route::middleware(['auth','role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

        // Пользователи
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users/{user}/toggle', [AdminController::class, 'toggleUser'])
            ->whereNumber('user')
            ->name('users.toggle');

        // Офферы
        Route::get('/offers', [AdminController::class, 'offers'])->name('offers');
        Route::post('/offers/{offer}/toggle', [AdminController::class, 'toggleOffer'])
            ->whereNumber('offer')
            ->name('offers.toggle');

        // Клики
        Route::get('/clicks', [AdminController::class, 'clicks'])->name('clicks');
        Route::get('/clicks/stats', [AdminController::class, 'clicksStats'])->name('clicks.stats');
        Route::get('/clicks/csv', [AdminController::class, 'clicksCsv'])->name('clicks.csv');

        // Доходы/расходы
        Route::get('/revenue/stats', [AdminController::class, 'revenueStats'])->name('revenue.stats');
        Route::get('/revenue/csv', [AdminController::class, 'revenueCsv'])->name('revenue.csv');

        // Подписки (выданные ссылки)
        Route::get('/subscriptions', [AdminController::class, 'subscriptions'])->name('subscriptions');
        Route::get('/subscriptions/csv', [AdminController::class, 'subscriptionsCsv'])->name('subscriptions.csv');

        // Темы
        Route::resource('topics', \App\Http\Controllers\Admin\TopicController::class)
            ->parameters(['topics' => 'topic'])
            ->names('topics');
    });

require __DIR__ . '/auth.php';
