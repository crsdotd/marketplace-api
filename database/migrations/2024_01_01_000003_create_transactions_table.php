<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 100);
            $table->string('account_number', 30);
            $table->string('account_holder');
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 30)->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('platform_fee', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2);
            $table->enum('type', ['cod', 'rekber'])->default('rekber');
            $table->enum('status', [
                'pending',
                'payment_confirmed',
                'processing',
                'shipped',
                'delivered',
                'completed',
                'refund_requested',
                'refunded',
                'cancelled',
                'cod_waiting',
                'cod_completed',
            ])->default('pending');
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('payment_deadline')->nullable();
            $table->timestamp('auto_complete_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
        });

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
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('description');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->text('description')->nullable();
            $table->json('evidence_images')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'processed'])->default('pending');
            $table->decimal('refund_amount', 15, 2);
            $table->string('refund_bank', 100)->nullable();
            $table->string('refund_account', 30)->nullable();
            $table->string('refund_holder')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('bank_name', 100);
            $table->string('bank_account', 30);
            $table->string('bank_holder');
            $table->enum('status', ['pending', 'processing', 'completed', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('transaction_timelines');
        Schema::dropIfExists('transaction_payments');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('platform_bank_accounts');
    }
};
