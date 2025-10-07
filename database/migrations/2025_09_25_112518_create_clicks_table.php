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
        Schema::create('clicks', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 64)->index(); // логируем даже если подписку не нашли
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referrer', 1024)->nullable();
            $table->timestamp('clicked_at')->useCurrent();
            $table->timestamp('redirected_at')->nullable();
            $table->boolean('is_valid')->default(false)->index();
            $table->string('invalid_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['subscription_id','is_valid','clicked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clicks');
    }
};
