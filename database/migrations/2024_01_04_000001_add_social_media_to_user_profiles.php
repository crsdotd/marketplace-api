<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Simpan sebagai JSON array
            // Contoh: [{"name":"Instagram","url":"https://instagram.com/xxx"},{"name":"Line","url":"https://line.me/xxx"}]
            $table->json('social_media')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('social_media');
        });
    }
};
