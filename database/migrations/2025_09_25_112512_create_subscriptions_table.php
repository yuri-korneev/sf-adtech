<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webmaster_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('cpc', 10, 4);           // ставка веб-мастера
            $table->string('token', 64)->unique();   // ссылка вида /r/{token}
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['offer_id','webmaster_id']);     // 1 подписка на оффер у конкретного WM
            $table->index(['webmaster_id','is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
