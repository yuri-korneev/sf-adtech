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
    Schema::create('offers', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->foreignId('advertiser_id')->constrained('users')->cascadeOnDelete();
        $table->string('name');
        $table->decimal('cpc', 10, 4);
        $table->string('target_url', 2048);
        $table->boolean('is_active')->default(true)->index();
        $table->timestamps();

        $table->index(['advertiser_id','is_active']);
    });
}

    /**
     * Reverse the migrations.
     */

public function down(): void
{
    Schema::dropIfExists('offers');
}

};
