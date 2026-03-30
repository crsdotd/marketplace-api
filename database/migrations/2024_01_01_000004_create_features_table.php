<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Ratings ──────────────────────────────────────────────
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['buyer_to_seller', 'seller_to_buyer']);
            $table->tinyInteger('rating'); // 1–5
            $table->text('review')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'rater_id', 'type']);
            $table->index(['rated_id', 'type']);
        });

        // ── Live Location (COD) ───────────────────────────────────
        Schema::create('live_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->boolean('is_sharing')->default(true);
            $table->string('share_token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('location_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_location_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->index('recorded_at');
        });

        Schema::create('meeting_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->enum('status', ['proposed', 'agreed', 'arrived'])->default('proposed');
            $table->foreignId('proposed_by')->constrained('users');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });

        // ── Ads / Promosi ─────────────────────────────────────────
        Schema::create('ad_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->integer('duration_days');
            $table->enum('type', ['homepage_banner', 'search_boost', 'category_top', 'featured'])->default('featured');
            $table->integer('impression_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_package_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending_payment', 'active', 'expired', 'paused', 'rejected'])->default('pending_payment');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_ad_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('bank_name', 100);
            $table->string('bank_account', 30);
            $table->string('proof_image')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // ── Chat Log ──────────────────────────────────────────────
        Schema::create('chat_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wa_number', 20);
            $table->text('initial_message')->nullable();
            $table->timestamps();
        });

        // ── Notifications ─────────────────────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('chat_contacts');
        Schema::dropIfExists('ad_payments');
        Schema::dropIfExists('product_ads');
        Schema::dropIfExists('ad_packages');
        Schema::dropIfExists('meeting_points');
        Schema::dropIfExists('location_histories');
        Schema::dropIfExists('live_locations');
        Schema::dropIfExists('ratings');
    }
};
