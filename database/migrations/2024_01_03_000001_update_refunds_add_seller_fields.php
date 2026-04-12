<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            // Tambah kolom untuk seller response
            $table->foreignId('seller_id')
                  ->nullable()
                  ->after('buyer_id')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->enum('status', [
                'pending',          // buyer ajukan refund
                'seller_reviewing', // seller sedang review
                'seller_approved',  // seller setuju refund
                'seller_rejected',  // seller tolak refund
                'admin_reviewing',  // eskalasi ke admin (jika seller tolak)
                'approved',         // admin approve (setelah eskalasi)
                'rejected',         // admin reject (setelah eskalasi)
                'processed',        // dana sudah dikembalikan
            ])->default('pending')->change();

            $table->text('seller_note')->nullable()->after('admin_note');
            $table->timestamp('seller_responded_at')->nullable()->after('seller_note');
            $table->boolean('is_escalated')->default(false)->after('seller_responded_at');
            $table->timestamp('escalated_at')->nullable()->after('is_escalated');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn([
                'seller_id', 'seller_note', 'seller_responded_at',
                'is_escalated', 'escalated_at',
            ]);
        });
    }
};
