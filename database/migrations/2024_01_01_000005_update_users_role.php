<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom role lama (enum buyer/seller/admin)
            $table->dropColumn('role');

            // Tambah kolom baru
            $table->boolean('is_buyer')->default(true)->after('password');   // semua user otomatis bisa beli
            $table->boolean('is_seller')->default(false)->after('is_buyer'); // seller harus diaktifkan dulu
            $table->boolean('is_admin')->default(false)->after('is_seller');
        });

        // Seller profile dibuat saat user aktifkan mode seller
        // Tidak perlu perubahan pada tabel seller_profiles
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_buyer', 'is_seller', 'is_admin']);
            $table->enum('role', ['buyer', 'seller', 'admin'])->default('buyer');
        });
    }
};
