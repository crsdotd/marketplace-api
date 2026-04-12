<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\RatingController;
use App\Http\Controllers\API\LiveLocationController;
use App\Http\Controllers\API\RefundController;
use App\Http\Controllers\API\AdController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\WalletController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\LocationController;
use App\Http\Controllers\API\SocialAuthController;

Route::prefix('v1')->group(function () {

    // ════════════════════════════════════════
    //  PUBLIC
    // ════════════════════════════════════════
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    // ── Social Authentication (Google & Facebook) ────
    Route::get('/auth/google',           [SocialAuthController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback',  [SocialAuthController::class, 'handleGoogleCallback']);
    Route::get('/login/google',          [SocialAuthController::class, 'redirectToGoogle']);
    Route::get('/login/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);

    Route::get('/auth/facebook',           [SocialAuthController::class, 'redirectToFacebook']);
    Route::get('/auth/facebook/callback',  [SocialAuthController::class, 'handleFacebookCallback']);
    Route::get('/login/facebook',          [SocialAuthController::class, 'redirectToFacebook']);
    Route::get('/login/facebook/callback', [SocialAuthController::class, 'handleFacebookCallback']);

    Route::get('/categories',                     [CategoryController::class, 'index']);
    Route::get('/categories/{category}/products', [CategoryController::class, 'products']);
    Route::get('/products',                       [ProductController::class,  'index']);
    Route::get('/products/{product}',             [ProductController::class,  'show']);

    Route::get('/ratings/seller/{userId}',     [RatingController::class, 'sellerRatings']);
    Route::get('/ratings/product/{productId}', [RatingController::class, 'productRatings']);
    Route::get('/live-location/track/{token}', [LiveLocationController::class, 'track']);
    Route::get('/ads/packages',                [AdController::class, 'packages']);
    Route::get('/bank-accounts',               [TransactionController::class, 'platformBankAccounts']);
    
    // ── Locations (Autocomplete) ──────────────
    Route::get('/locations/search',       [LocationController::class, 'search']);
    Route::get('/locations/suggestions',  [LocationController::class, 'suggestions']);
    Route::get('/locations/reverse',      [LocationController::class, 'reverse']);

    // Midtrans akan POST ke URL ini setiap ada update pembayaran
    Route::post('/payment/notification', [PaymentController::class, 'notification']);
    // Metode pembayaran (publik, untuk tampil di UI)
    Route::get('/payment/methods', [PaymentController::class, 'methods']);


    // ════════════════════════════════════════
    //  AUTHENTICATED
    // ════════════════════════════════════════
    Route::middleware('auth:sanctum')->group(function () {

        // ── Auth & Profil ─────────────────────────────
        Route::post('/logout',          [AuthController::class, 'logout']);
        Route::get('/me',               [AuthController::class, 'me']);
        Route::put('/me',               [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        // ── Social Account Management ────────────────
        Route::delete('/auth/social/{provider}', [SocialAuthController::class, 'unlinkSocialAccount']);

        // ── Aktivasi Seller ───────────────────────────
        // Dipanggil saat user klik "Mulai Berjualan" di aplikasi
        Route::post('/seller/activate',   [AuthController::class, 'activateSeller']);
        Route::put('/seller/profile',     [AuthController::class, 'updateSellerProfile']);
        Route::post('/seller/deactivate', [AuthController::class, 'deactivateSeller']);

        // ── Produk (butuh role seller) ────────────────
        Route::post('/products',             [ProductController::class, 'store']);
        Route::put('/products/{product}',    [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::get('/my/products',           [ProductController::class, 'myProducts']);

        // Favorit (semua user bisa)
        Route::post('/products/{product}/favorite', [ProductController::class, 'toggleFavorite']);
        Route::get('/my/favorites',                 [ProductController::class, 'favorites']);

        // ── Transaksi ─────────────────────────────────
        Route::prefix('transactions')->group(function () {
            Route::get('/',                       [TransactionController::class, 'index']);
            Route::post('/',                      [TransactionController::class, 'store']);
            Route::get('/{transaction}',          [TransactionController::class, 'show']);
            Route::post('/{transaction}/payment', [TransactionController::class, 'uploadPayment']);
            Route::post('/{transaction}/confirm', [TransactionController::class, 'confirmReceived']);
            Route::put('/{transaction}/status',   [TransactionController::class, 'updateStatus']);

            // Rating setelah transaksi selesai
            Route::post('/{transaction}/rate-seller', [RatingController::class, 'rateAsBuyer']);
            Route::post('/{transaction}/rate-buyer',  [RatingController::class, 'rateAsSeller']);
        });

        // Buat pembayaran Midtrans (generate Snap Token)
        Route::post('/payment/create/{transaction}', [PaymentController::class, 'create']);

        // Cek status pembayaran
        Route::get('/payment/status/{transaction}', [PaymentController::class, 'status']);

        // Batalkan pembayaran
        Route::post('/payment/cancel/{transaction}', [PaymentController::class, 'cancel']);

        // ── Live Location (COD) ───────────────────────
        Route::prefix('live-location')->group(function () {
            Route::post('/start',                             [LiveLocationController::class, 'start']);
            Route::put('/{liveLocation}/update',              [LiveLocationController::class, 'update']);
            Route::post('/{liveLocation}/stop',               [LiveLocationController::class, 'stop']);
            Route::post('/meeting-point',                     [LiveLocationController::class, 'proposeMeetingPoint']);
            Route::put('/meeting-point/{meetingPoint}/agree', [LiveLocationController::class, 'agreeMeetingPoint']);
        });

        // ── Refund ────────────────────────────────────
        // ── BUYER: Refund ─────────────────────────────────────────────
        // Ganti route refund yang lama dengan yang baru ini

        Route::prefix('refunds')->group(function () {
            // Buyer: list refund saya
            Route::get('/', [RefundController::class, 'index']);

            // Buyer: ajukan refund baru
            Route::post('/', [RefundController::class, 'store']);

            // Buyer & Seller: detail refund
            Route::get('/{refund}', [RefundController::class, 'show']);

            // Buyer: eskalasi ke admin jika seller menolak
            Route::post('/{refund}/escalate', [RefundController::class, 'escalate']);
        });


        // ── SELLER: Tangani Refund ────────────────────────────────────
        Route::prefix('seller/refunds')->group(function () {
            // Seller: list semua refund yang masuk
            // ?status=pending | seller_reviewing | seller_approved | seller_rejected
            Route::get('/', [RefundController::class, 'sellerIndex']);

            // Seller: mulai review (ubah status ke reviewing)
            Route::put('/{refund}/review', [RefundController::class, 'sellerReview']);

            // Seller: setujui refund
            Route::post('/{refund}/approve', [RefundController::class, 'sellerApprove']);

            // Seller: tolak refund (wajib isi seller_note)
            Route::post('/{refund}/reject', [RefundController::class, 'sellerReject']);
        });

        // ── Ads ───────────────────────────────────────
        Route::get('/ads/packages', [AdController::class, 'packages']);
        Route::post('/ads',         [AdController::class, 'store']);
        Route::get('/ads/my',       [AdController::class, 'myAds']);

        // ── Chat ──────────────────────────────────────
        Route::post('/chat/whatsapp', [ChatController::class, 'whatsappLink']);

        // ── Wallet ────────────────────────────────────
        Route::get('/wallet/balance',   [WalletController::class, 'balance']);
        Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
        Route::get('/wallet/history',   [WalletController::class, 'history']);

        // ── Notifikasi ────────────────────────────────
        Route::get('/notifications', function (Request $request) {
            return response()->json([
                'success' => true,
                'data'    => $request->user()->notifications()->orderByDesc('created_at')->paginate(20),
            ]);
        });
        Route::put('/notifications/{id}/read', function (string $id, Request $request) {
            $request->user()->notifications()->findOrFail($id)->markAsRead();
            return response()->json(['success' => true]);
        });
        Route::put('/notifications/read-all', function (Request $request) {
            $request->user()->unreadNotifications->markAsRead();
            return response()->json(['success' => true]);
        });

    });
});
