<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('barter_requests', function (Blueprint $table) {
            $table->id();

            // Produk yang ingin dibarter (milik seller)
            $table->foreignId('product_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Buyer yang mengajukan barter
            $table->foreignId('buyer_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Seller pemilik produk
            $table->foreignId('seller_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // ── Data barang yang ditawarkan buyer ──────────────────
            $table->string('offer_item_name');              // Nama barang yang ditawarkan
            $table->foreignId('offer_category_id')          // Kategori barang yang ditawarkan
                  ->constrained('categories')
                  ->cascadeOnDelete();
            $table->text('offer_description');              // Deskripsi barang yang ditawarkan
            $table->json('offer_images');                   // Foto-foto barang yang ditawarkan
            $table->decimal('offer_additional_price', 15, 2)->default(0); // Tambahan uang jika ada selisih harga

            // ── Status barter ──────────────────────────────────────
            $table->enum('status', [
                'pending',          // Buyer mengajukan, menunggu respon seller
                'seller_reviewing', // Seller sedang mempertimbangkan
                'accepted',         // Seller menerima — lanjut ke pembayaran selisih (jika ada)
                'rejected',         // Seller menolak
                'payment_pending',  // Menunggu pembayaran selisih via Midtrans
                'payment_confirmed',// Pembayaran selisih dikonfirmasi
                'completed',        // Barter selesai
                'cancelled',        // Dibatalkan salah satu pihak
            ])->default('pending');

            $table->text('seller_note')->nullable();        // Catatan dari seller
            $table->text('buyer_note')->nullable();         // Catatan dari buyer
            $table->timestamp('seller_responded_at')->nullable();

            // Midtrans untuk pembayaran selisih (jika ada)
            $table->string('midtrans_snap_token')->nullable();
            $table->string('midtrans_order_id')->nullable();
            $table->string('midtrans_redirect_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barter_requests');
    }
};
