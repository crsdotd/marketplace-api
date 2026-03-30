<?php

use Illuminate\Support\Facades\Route;
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

/*
|--------------------------------------------------------------------------
| Marketplace API Routes  —  Prefix: /api/v1/
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ════════════════════════════════════════
    //  PUBLIC (no auth needed)
    // ════════════════════════════════════════

    // Auth
    Route::post('/register',        [AuthController::class, 'register']);
    Route::post('/login',           [AuthController::class, 'login']);

    // Kategori
    Route::get('/categories',                      [CategoryController::class, 'index']);
    Route::get('/categories/{category}/products',  [CategoryController::class, 'products']);

    // Produk
    Route::get('/products',        [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Rating
    Route::get('/ratings/seller/{userId}',     [RatingController::class, 'sellerRatings']);
    Route::get('/ratings/product/{productId}', [RatingController::class, 'productRatings']);

    // Live location via token (COD tracking tanpa login)
    Route::get('/live-location/track/{token}', [LiveLocationController::class, 'track']);

    // Paket iklan & Rekening platform (publik)
    Route::get('/ads/packages',  [AdController::class, 'packages']);
    Route::get('/bank-accounts', [TransactionController::class, 'platformBankAccounts']);


    // ════════════════════════════════════════
    //  AUTHENTICATED (Sanctum token required)
    // ════════════════════════════════════════

    Route::middleware('auth:sanctum')->group(function () {

        // ── Auth ──────────────────────────────────
        Route::post('/logout',           [AuthController::class, 'logout']);
        Route::get('/me',                [AuthController::class, 'me']);
        Route::put('/me',                [AuthController::class, 'updateProfile']);
        Route::post('/change-password',  [AuthController::class, 'changePassword']);

        // ── Produk (seller) ───────────────────────
        Route::post('/products',              [ProductController::class, 'store']);
        Route::put('/products/{product}',     [ProductController::class, 'update']);
        Route::delete('/products/{product}',  [ProductController::class, 'destroy']);
        Route::get('/my/products',            [ProductController::class, 'myProducts']);

        // Favorit
        Route::post('/products/{product}/favorite', [ProductController::class, 'toggleFavorite']);
        Route::get('/my/favorites',                 [ProductController::class, 'favorites']);

        // ── Transaksi ─────────────────────────────
        Route::prefix('transactions')->group(function () {
            Route::get('/',                          [TransactionController::class, 'index']);
            Route::post('/',                         [TransactionController::class, 'store']);
            Route::get('/{transaction}',             [TransactionController::class, 'show']);
            Route::post('/{transaction}/payment',    [TransactionController::class, 'uploadPayment']);
            Route::post('/{transaction}/confirm',    [TransactionController::class, 'confirmReceived']);
            Route::put('/{transaction}/status',      [TransactionController::class, 'updateStatus']);
        });

        // ── Rating ────────────────────────────────
        Route::post('/ratings', [RatingController::class, 'store']);

        // ── Live Location (COD) ───────────────────
        Route::prefix('live-location')->group(function () {
            Route::post('/start',                              [LiveLocationController::class, 'start']);
            Route::put('/{liveLocation}/update',               [LiveLocationController::class, 'update']);
            Route::post('/{liveLocation}/stop',                [LiveLocationController::class, 'stop']);
            Route::post('/meeting-point',                      [LiveLocationController::class, 'proposeMeetingPoint']);
            Route::put('/meeting-point/{meetingPoint}/agree',  [LiveLocationController::class, 'agreeMeetingPoint']);
        });

        // ── Refund ────────────────────────────────
        Route::prefix('refunds')->group(function () {
            Route::get('/',        [RefundController::class, 'index']);
            Route::post('/',       [RefundController::class, 'store']);
            Route::get('/{refund}',[RefundController::class, 'show']);
        });

        // ── Ads / Promosi ─────────────────────────
        Route::post('/ads',     [AdController::class, 'store']);
        Route::get('/ads/my',   [AdController::class, 'myAds']);

        // ── Chat → WhatsApp ───────────────────────
        Route::post('/chat/whatsapp', [ChatController::class, 'whatsappLink']);

        // ── Wallet / Saldo ────────────────────────
        Route::prefix('wallet')->group(function () {
            Route::get('/balance',   [WalletController::class, 'balance']);
            Route::post('/withdraw', [WalletController::class, 'withdraw']);
            Route::get('/history',   [WalletController::class, 'history']);
        });

        // ── Notifikasi ────────────────────────────
        Route::prefix('notifications')->group(function () {
            Route::get('/', function (Illuminate\Http\Request $request) {
                return response()->json([
                    'success' => true,
                    'data'    => $request->user()->notifications()->orderByDesc('created_at')->paginate(20),
                ]);
            });
            Route::put('/{id}/read', function (string $id, Illuminate\Http\Request $request) {
                $notif = $request->user()->notifications()->findOrFail($id);
                $notif->markAsRead();
                return response()->json(['success' => true, 'message' => 'Notifikasi ditandai sudah dibaca.']);
            });
            Route::put('/read-all', function (Illuminate\Http\Request $request) {
                $request->user()->unreadNotifications->markAsRead();
                return response()->json(['success' => true, 'message' => 'Semua notifikasi ditandai sudah dibaca.']);
            });
        });

    }); // end auth:sanctum

}); // end v1
