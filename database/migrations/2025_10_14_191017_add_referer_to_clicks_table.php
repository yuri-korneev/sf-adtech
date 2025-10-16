<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $t) {
            if (!Schema::hasColumn('clicks', 'referer')) {
                $t->string('referer', 1024)->nullable()->after('user_agent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $t) {
            if (Schema::hasColumn('clicks', 'referer')) {
                $t->dropColumn('referer');
            }
        });
    }
};
