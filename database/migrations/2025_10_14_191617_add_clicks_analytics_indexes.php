<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Хелпер проверки существования индекса (идемпотентность)
        $hasIndex = static function (string $name): bool {
            return DB::selectOne("SHOW INDEX FROM `clicks` WHERE Key_name = ?", [$name]) !== null;
        };

        Schema::table('clicks', function (Blueprint $t) use ($hasIndex) {
            // Добавляем колонку redirected_at, если её ещё нет
            if (!Schema::hasColumn('clicks', 'redirected_at')) {
                $t->timestamp('redirected_at')->nullable()->after('clicked_at');
            }

            // Индексы — добавляем только если их ещё нет
            if (!$hasIndex('clicks_subscription_id_index')) {
                $t->index('subscription_id', 'clicks_subscription_id_index');
            }
            if (!$hasIndex('clicks_token_index')) {
                $t->index('token', 'clicks_token_index');
            }
            if (!$hasIndex('clicks_clicked_at_index')) {
                $t->index('clicked_at', 'clicks_clicked_at_index');
            }
            if (!$hasIndex('clicks_redirected_at_index')) {
                $t->index('redirected_at', 'clicks_redirected_at_index');
            }
            if (!$hasIndex('clicks_is_valid_index')) {
                $t->index('is_valid', 'clicks_is_valid_index');
            }
        });
    }

    public function down(): void
    {
        // Хелпер проверки существования индекса
        $hasIndex = static function (string $name): bool {
            return DB::selectOne("SHOW INDEX FROM `clicks` WHERE Key_name = ?", [$name]) !== null;
        };

        Schema::table('clicks', function (Blueprint $t) use ($hasIndex) {
            // Снимаем индексы только если они существуют
            if ($hasIndex('clicks_subscription_id_index')) {
                $t->dropIndex('clicks_subscription_id_index');
            }
            if ($hasIndex('clicks_token_index')) {
                $t->dropIndex('clicks_token_index');
            }
            if ($hasIndex('clicks_clicked_at_index')) {
                $t->dropIndex('clicks_clicked_at_index');
            }
            if ($hasIndex('clicks_redirected_at_index')) {
                $t->dropIndex('clicks_redirected_at_index');
            }
            if ($hasIndex('clicks_is_valid_index')) {
                $t->dropIndex('clicks_is_valid_index');
            }

            // Колонку redirected_at не трогаем в down(), чтобы не потерять данные;
            // при необходимости можно раскомментировать:
            // if (Schema::hasColumn('clicks', 'redirected_at')) {
            //     $t->dropColumn('redirected_at');
            // }
        });
    }
};
