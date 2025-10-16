public function up(): void
{
    Schema::table('clicks', function (Blueprint $t) {
        $t->index('clicked_at');
        $t->index(['is_valid','clicked_at']);
        $t->index(['offer_id','clicked_at']);
        $t->index(['webmaster_id','clicked_at']);
        $t->index(['advertiser_id','clicked_at']);
        $t->index('token');
    });
}

public function down(): void
{
    Schema::table('clicks', function (Blueprint $t) {
        $t->dropIndex(['clicked_at']);
        $t->dropIndex(['is_valid','clicked_at']);
        $t->dropIndex(['offer_id','clicked_at']);
        $t->dropIndex(['webmaster_id','clicked_at']);
        $t->dropIndex(['advertiser_id','clicked_at']);
        $t->dropIndex(['token']);
    });
}
