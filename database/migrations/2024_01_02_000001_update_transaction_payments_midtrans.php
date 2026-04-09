<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Hapus tabel lama (manual bank transfer)
        Schema::dropIfExists('transaction_payments');

        // Buat tabel baru dengan support Midtrans
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();

            // Midtrans fields
            $table->string('midtrans_order_id')->unique();       // TRX-xxx (sama dengan transaction_code)
            $table->string('midtrans_transaction_id')->nullable(); // ID dari Midtrans
            $table->string('midtrans_token')->nullable();          // Snap token untuk frontend
            $table->string('midtrans_redirect_url')->nullable();   // URL Snap payment page
            $table->string('midtrans_va_number')->nullable();      // Nomor VA jika pakai transfer bank
            $table->string('midtrans_payment_type')->nullable();   // gopay, bank_transfer, qris, dll

            // Payment info
            $table->decimal('amount', 15, 2);
            $table->enum('status', [
                'pending',    // menunggu pembayaran
                'success',    // pembayaran berhasil
                'failure',    // pembayaran gagal
                'expire',     // kadaluarsa
                'cancel',     // dibatalkan
                'refund',     // sudah direfund
            ])->default('pending');

            $table->json('midtrans_response')->nullable(); // raw response dari Midtrans
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index('midtrans_order_id');
            $table->index(['transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['bank_transfer', 'cod']);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 30)->nullable();
            $table->string('bank_holder')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('proof_image')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }
};
