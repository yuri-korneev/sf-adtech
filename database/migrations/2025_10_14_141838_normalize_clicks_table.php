<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            // Идентификаторы сущностей
            if (!Schema::hasColumn('clicks', 'subscription_id')) {
                $table->unsignedBigInteger('subscription_id')->nullable()->after('id');
                $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            }
            if (!Schema::hasColumn('clicks', 'offer_id')) {
                $table->unsignedBigInteger('offer_id')->nullable()->after('subscription_id');
                $table->foreign('offer_id')->references('id')->on('offers')->nullOnDelete();
            }
            if (!Schema::hasColumn('clicks', 'webmaster_id')) {
                $table->unsignedBigInteger('webmaster_id')->nullable()->after('offer_id');
                $table->foreign('webmaster_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('clicks', 'advertiser_id')) {
                $table->unsignedBigInteger('advertiser_id')->nullable()->after('webmaster_id');
                $table->foreign('advertiser_id')->references('id')->on('users')->nullOnDelete();
            }

            // Технические поля клика
            if (!Schema::hasColumn('clicks', 'token')) {
                $table->string('token', 191)->nullable()->after('advertiser_id');
            }
            if (!Schema::hasColumn('clicks', 'ip')) {
                $table->string('ip', 45)->nullable()->after('token'); // IPv6
            }
            if (!Schema::hasColumn('clicks', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip');
            }
            if (!Schema::hasColumn('clicks', 'referrer')) {
                $table->text('referrer')->nullable()->after('user_agent');
            }

            // Временные метки и статус
            if (!Schema::hasColumn('clicks', 'clicked_at')) {
                $table->timestamp('clicked_at')->nullable()->after('referrer');
            }
            if (!Schema::hasColumn('clicks', 'redirected_at')) {
                $table->timestamp('redirected_at')->nullable()->after('clicked_at');
            }
            if (!Schema::hasColumn('clicks', 'is_valid')) {
                $table->boolean('is_valid')->default(true)->after('redirected_at');
            }
            if (!Schema::hasColumn('clicks', 'invalid_reason')) {
                $table->string('invalid_reason', 64)->nullable()->after('is_valid');
            }

            // Денежные поля (опционально для отчётов)
            if (!Schema::hasColumn('clicks', 'adv_cost')) {
                $table->decimal('adv_cost', 10, 4)->nullable()->after('invalid_reason'); // расход рекламодателя
            }
            if (!Schema::hasColumn('clicks', 'wm_payout')) {
                $table->decimal('wm_payout', 10, 4)->nullable()->after('adv_cost'); // доход вебмастера
            }

            // Индексы
            $table->index('token', 'clicks_token_idx');
            $table->index(['is_valid', 'clicked_at'], 'clicks_valid_time_idx');
            $table->index(['invalid_reason', 'clicked_at'], 'clicks_reason_time_idx');
            $table->index(['offer_id', 'clicked_at'], 'clicks_offer_time_idx');
            $table->index(['webmaster_id', 'clicked_at'], 'clicks_wm_time_idx');
            $table->index(['advertiser_id', 'clicked_at'], 'clicks_adv_time_idx');
        });
    }

    public function down(): void
    {
        // Оставляем как есть (миграция нормализации — безопаснее не откатывать поля назад)
    }
};
