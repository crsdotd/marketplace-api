<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tambah kolom place_id ke products table untuk menyimpan Google Maps place_id
        Schema::table('products', function (Blueprint $table) {
            $table->string('location_place_id')->nullable()->after('longitude');
            $table->string('location_address')->nullable()->after('location_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['location_place_id', 'location_address']);
        });
    }
};
