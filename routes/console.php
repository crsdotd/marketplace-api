<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Transaction;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled Jobs ─────────────────────────────────────────

// Auto-complete transaksi yang sudah lewat deadline (setiap jam)
Schedule::call(function () {
    $transactions = Transaction::where('status', 'shipped')
        ->where('auto_complete_at', '<=', Carbon::now())
        ->get();

    foreach ($transactions as $transaction) {
        $transaction->update(['status' => 'completed']);
        $transaction->seller->increment('balance', $transaction->total_price);
        $transaction->seller->sellerProfile?->increment('total_sold');

        \App\Models\TransactionTimeline::create([
            'transaction_id' => $transaction->id,
            'status'         => 'completed',
            'description'    => 'Transaksi otomatis diselesaikan (7 hari tanpa konfirmasi).',
        ]);
    }
})->hourly()->name('auto-complete-transactions');

// Hapus live location expired setiap 30 menit
Schedule::call(function () {
    \App\Models\LiveLocation::where('expires_at', '<', Carbon::now())
        ->update(['is_sharing' => false]);
})->everyThirtyMinutes()->name('cleanup-expired-locations');

// Aktifkan iklan yang sudah dibayar (cek setiap 15 menit)
Schedule::call(function () {
    \App\Models\ProductAd::where('status', 'active')
        ->where('expires_at', '<=', Carbon::now())
        ->each(function ($ad) {
            $ad->update(['status' => 'expired']);
            $ad->product->update(['is_promoted' => false, 'promoted_until' => null]);
        });
})->everyFifteenMinutes()->name('expire-product-ads');
