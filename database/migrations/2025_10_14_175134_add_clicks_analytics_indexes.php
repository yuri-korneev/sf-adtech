<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            // Быстрый поиск свежей записи по токену и времени клика
            $table->index(['token', 'clicked_at'], 'clicks_token_clicked_idx');

            // Отчёты и фильтры по валидам
            $table->index(['is_valid', 'invalid_reason'], 'clicks_valid_reason_idx');

            // Быстрые выборки «за период»
            $table->index('created_at', 'clicks_created_idx');
            $table->index('clicked_at', 'clicks_clicked_idx');

            // Отметка редиректа
            $table->index('redirected_at', 'clicks_redirected_idx');

            // Частые связи в отчётах
            $table->index('subscription_id', 'clicks_sub_idx');
            $table->index('offer_id', 'clicks_offer_idx');
            $table->index('webmaster_id', 'clicks_wm_idx');
            $table->index('advertiser_id', 'clicks_adv_idx');
        });
    }

    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->dropIndex('clicks_token_clicked_idx');
            $table->dropIndex('clicks_valid_reason_idx');
            $table->dropIndex('clicks_created_idx');
            $table->dropIndex('clicks_clicked_idx');
            $table->dropIndex('clicks_redirected_idx');
            $table->dropIndex('clicks_sub_idx');
            $table->dropIndex('clicks_offer_idx');
            $table->dropIndex('clicks_wm_idx');
            $table->dropIndex('clicks_adv_idx');
        });
    }
};
