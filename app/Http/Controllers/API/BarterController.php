<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BarterRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BarterController extends Controller
{
    // ════════════════════════════════════════
    //  BUYER — Ajukan tukar tambah
    // ════════════════════════════════════════

    /**
     * POST /api/v1/barter
     * Buyer mengajukan permintaan tukar tambah
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id'             => 'required|exists:products,id',
            'offer_item_name'        => 'required|string|max:255',
            'offer_category_id'      => 'required|exists:categories,id',
            'offer_description'      => 'required|string|max:2000',
            'offer_images'           => 'required|array|min:1|max:5',
            'offer_images.*'         => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'offer_additional_price' => 'sometimes|numeric|min:0',
            'buyer_note'             => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($request->product_id);

        // Tidak bisa barter produk sendiri
        if ($product->user_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa mengajukan tukar tambah untuk produk sendiri.',
            ], 422);
        }

        // Produk harus aktif
        if ($product->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak tersedia untuk tukar tambah.',
            ], 422);
        }

        // Cek tidak ada barter aktif untuk produk ini dari buyer yang sama
        $existingBarter = BarterRequest::where('product_id', $product->id)
            ->where('buyer_id', $request->user()->id)
            ->whereIn('status', ['pending', 'seller_reviewing', 'accepted', 'payment_pending'])
            ->exists();

        if ($existingBarter) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu sudah punya permintaan tukar tambah aktif untuk produk ini.',
            ], 422);
        }

        // Upload foto barang yang ditawarkan
        $images = [];
        foreach ($request->file('offer_images') as $img) {
            $images[] = $img->store('barter-offers', 'public');
        }

        DB::beginTransaction();
        try {
            $barter = BarterRequest::create([
                'product_id'             => $product->id,
                'buyer_id'               => $request->user()->id,
                'seller_id'              => $product->user_id,
                'offer_item_name'        => $request->offer_item_name,
                'offer_category_id'      => $request->offer_category_id,
                'offer_description'      => $request->offer_description,
                'offer_images'           => $images,
                'offer_additional_price' => $request->offer_additional_price ?? 0,
                'buyer_note'             => $request->buyer_note,
            ]);

            // Notifikasi ke seller
            $product->seller->notify(new \App\Notifications\BarterRequestReceived($barter));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan tukar tambah berhasil dikirim ke penjual.',
                'data'    => $barter->load(['product.images', 'offerCategory', 'seller']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan tukar tambah: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/barter
     * List barter milik buyer
     */
    public function buyerIndex(Request $request): JsonResponse
    {
        $barters = BarterRequest::with([
            'product.images', 'offerCategory', 'seller.sellerProfile',
        ])
        ->where('buyer_id', $request->user()->id)
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->orderByDesc('created_at')
        ->paginate(15);

        // ✅ Sync status Midtrans untuk semua barter yang payment_pending
        foreach ($barters->items() as $barter) {
            if ($barter->status === 'payment_pending' && $barter->midtrans_order_id) {
                $this->syncMidtransStatus($barter);
            }
        }

        // Refresh data setelah sync
        return response()->json([
            'success' => true,
            'data'    => $barters,
        ]);
    }

    /**
     * GET /api/v1/barter/{id}
     * Detail barter — buyer atau seller bisa akses
     */
    public function show(Request $request, BarterRequest $barter): JsonResponse
    {
        $user = $request->user();

        if ($barter->buyer_id !== $user->id && $barter->seller_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        // ✅ Sinkronisasi status Midtrans jika masih payment_pending
        if ($barter->status === 'payment_pending' && $barter->midtrans_order_id) {
        $this->syncMidtransStatus($barter);
        }

        return response()->json([
            'success' => true,
            'data'    => $barter->fresh()->load([
                'product.images', 'offerCategory',
                'buyer.profile', 'seller.sellerProfile',
            ]),
        ]);
    }

    /**
     * POST /api/v1/barter/{id}/cancel
     * Buyer batalkan permintaan barter
     */
    public function cancel(Request $request, BarterRequest $barter): JsonResponse
    {
        if ($barter->buyer_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (!in_array($barter->status, ['pending', 'seller_reviewing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Barter tidak bisa dibatalkan pada status ini.',
            ], 422);
        }

        $barter->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan tukar tambah berhasil dibatalkan.',
        ]);
    }

    // ════════════════════════════════════════
    //  SELLER — Tangani permintaan barter
    // ════════════════════════════════════════

    /**
     * GET /api/v1/seller/barter
     * Daftar barter yang masuk ke seller
     */
    public function sellerIndex(Request $request): JsonResponse
    {
        $barters = BarterRequest::with([
            'product.images', 'offerCategory', 'buyer.profile',
        ])
        ->where('seller_id', $request->user()->id)
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->orderByDesc('created_at')
        ->paginate(15);

        // ✅ Sync status Midtrans untuk semua barter yang payment_pending
        foreach ($barters->items() as $barter) {
            if ($barter->status === 'payment_pending' && $barter->midtrans_order_id) {
                $this->syncMidtransStatus($barter);
            }
        }

        // Refresh data setelah sync
        return response()->json([
            'success' => true,
            'data'    => $barters,
        ]);
    }

    /**
     * POST /api/v1/seller/barter/{id}/accept
     * Seller menerima permintaan barter
     * Jika offer_additional_price > 0, buyer perlu bayar selisih dulu via Midtrans
     */
    public function sellerAccept(Request $request, BarterRequest $barter): JsonResponse
    {
        if ($barter->seller_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (!in_array($barter->status, ['pending', 'seller_reviewing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Barter tidak bisa diterima pada status ini.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'seller_note'            => 'sometimes|string|max:500',
            // Seller bisa set nominal tambahan yang harus dibayar buyer
            'offer_additional_price' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $additionalPrice = $request->offer_additional_price ?? $barter->offer_additional_price;

            $barter->update([
                'status'                 => $additionalPrice > 0 ? 'payment_pending' : 'accepted',
                'seller_note'            => $request->seller_note,
                'offer_additional_price' => $additionalPrice,
                'seller_responded_at'    => now(),
            ]);

            // Jika ada biaya tambahan — generate Snap Token Midtrans
            if ($additionalPrice > 0) {
                $snapData = $this->generateBarterSnapToken($barter, $additionalPrice);

                if ($snapData) {
                    $barter->update([
                        'midtrans_snap_token'   => $snapData['snap_token'],
                        'midtrans_order_id'     => $snapData['order_id'],
                        'midtrans_redirect_url' => $snapData['redirect_url'],
                    ]);
                }
            }

            // Notifikasi ke buyer
            $barter->buyer->notify(new \App\Notifications\BarterRequestResponded($barter, 'accepted'));

            DB::commit();

            $message = $additionalPrice > 0
                ? "Tukar tambah diterima! Buyer perlu membayar selisih Rp " . number_format($additionalPrice, 0, ',', '.') . "."
                : "Tukar tambah diterima!";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $barter->fresh()->load(['product', 'buyer', 'offerCategory']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menerima barter: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/seller/barter/{id}/reject
     * Seller menolak permintaan barter
     */
    public function sellerReject(Request $request, BarterRequest $barter): JsonResponse
    {
        if ($barter->seller_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (!in_array($barter->status, ['pending', 'seller_reviewing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Barter tidak bisa ditolak pada status ini.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'seller_note' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $barter->update([
            'status'              => 'rejected',
            'seller_note'         => $request->seller_note,
            'seller_responded_at' => now(),
        ]);

        // Notifikasi ke buyer
        $barter->buyer->notify(new \App\Notifications\BarterRequestResponded($barter, 'rejected'));

        return response()->json([
            'success' => true,
            'message' => 'Permintaan barter ditolak.',
        ]);
    }

    // ════════════════════════════════════════
    //  PAYMENT — Bayar selisih barter via Midtrans
    // ════════════════════════════════════════

    /**
     * POST /api/v1/barter/{id}/pay
     * Buyer bayar selisih harga barter via Midtrans
     */
    public function payAdditional(Request $request, BarterRequest $barter): JsonResponse
    {
        if ($barter->buyer_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if ($barter->status !== 'payment_pending') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada pembayaran yang diperlukan.',
            ], 422);
        }

        // Jika sudah ada snap token yang valid — kembalikan
        if ($barter->midtrans_snap_token) {
            return response()->json([
                'success' => true,
                'message' => 'Link pembayaran sudah ada.',
                'data'    => [
                    'snap_token'    => $barter->midtrans_snap_token,
                    'redirect_url'  => $barter->midtrans_redirect_url,
                    'order_id'      => $barter->midtrans_order_id,
                    'amount'        => $barter->offer_additional_price,
                    'client_key'    => config('midtrans.client_key'),
                    'is_production' => config('midtrans.is_production'),
                ],
            ]);
        }

        $snapData = $this->generateBarterSnapToken($barter, $barter->offer_additional_price);

        if (!$snapData) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat link pembayaran.',
            ], 500);
        }

        $barter->update([
            'midtrans_snap_token'   => $snapData['snap_token'],
            'midtrans_order_id'     => $snapData['order_id'],
            'midtrans_redirect_url' => $snapData['redirect_url'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'snap_token'    => $snapData['snap_token'],
                'redirect_url'  => $snapData['redirect_url'],
                'order_id'      => $snapData['order_id'],
                'amount'        => $barter->offer_additional_price,
                'client_key'    => config('midtrans.client_key'),
                'is_production' => config('midtrans.is_production'),
            ],
        ]);
    }

    /**
     * POST /api/v1/barter/payment/notification
     * Webhook Midtrans untuk pembayaran selisih barter (PUBLIC)
     */
    public function paymentNotification(Request $request): JsonResponse
    {
        $this->initMidtrans();

        try {
            $notification  = new \Midtrans\Notification();
            $orderId       = $notification->order_id;
            $txStatus      = $notification->transaction_status;
            $fraudStatus   = $notification->fraud_status;
            $statusCode    = $notification->status_code;
            $grossAmount   = $notification->gross_amount;
            $signatureKey  = $notification->signature_key;

            // Verifikasi signature
            $expectedSig = hash('sha512', $orderId . $statusCode . $grossAmount . config('midtrans.server_key'));
            if ($signatureKey !== $expectedSig) {
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            $barter = BarterRequest::where('midtrans_order_id', $orderId)->first();
            if (!$barter) {
                return response()->json(['message' => 'Barter not found'], 404);
            }

            if (in_array($txStatus, ['settlement', 'capture']) && $fraudStatus !== 'deny') {
                $barter->update(['status' => 'payment_confirmed']);

                // Notifikasi ke seller bahwa buyer sudah bayar selisih
                $barter->seller->notify(new \App\Notifications\BarterRequestResponded($barter, 'payment_confirmed'));
            } elseif (in_array($txStatus, ['expire', 'cancel', 'deny'])) {
                $barter->update(['status' => 'payment_pending', 'midtrans_snap_token' => null]);
            }

            return response()->json(['message' => 'OK']);

        } catch (\Exception $e) {
            Log::error('Barter payment notification error: ' . $e->getMessage());
            return response()->json(['message' => 'Error'], 500);
        }
    }

    /**
     * POST /api/v1/seller/barter/{id}/complete
     * Seller konfirmasi barter selesai (barang sudah ditukar)
     */
    public function sellerComplete(Request $request, BarterRequest $barter): JsonResponse
    {
        if ($barter->seller_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        // ✅ Sync dulu sebelum validasi status
        if ($barter->status === 'payment_pending' && $barter->midtrans_order_id) {
            $this->syncMidtransStatus($barter);
            $barter->refresh(); // Ambil status terbaru setelah sync
        }

        $allowedStatuses = ['accepted', 'payment_confirmed'];
        if (!in_array($barter->status, $allowedStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Barter belum siap diselesaikan.',
            ], 422);
        }

        $barter->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'Tukar tambah berhasil diselesaikan!',
            'data'    => $barter->fresh(),
        ]);
    }

    // ════════════════════════════════════════
    //  HELPER
    // ════════════════════════════════════════

    private function initMidtrans(): void
    {
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$clientKey    = config('midtrans.client_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production');
        \Midtrans\Config::$isSanitized  = config('midtrans.is_sanitized');
        \Midtrans\Config::$is3ds        = config('midtrans.is_3ds');
    }

    private function syncMidtransStatus(BarterRequest $barter): void
    {
        try {
            $this->initMidtrans();

            $statusMidtrans = \Midtrans\Transaction::status($barter->midtrans_order_id);
            $mtStatus       = $statusMidtrans->transaction_status ?? null;
            $fraudStatus    = $statusMidtrans->fraud_status ?? null;

            // Jika sudah settlement atau capture → ubah ke payment_confirmed
            if (in_array($mtStatus, ['settlement', 'capture']) && $fraudStatus !== 'deny') {
                $barter->update(['status' => 'payment_confirmed']);

                // Notifikasi ke seller bahwa buyer sudah bayar
                try {
                    $barter->seller->notify(
                        new \App\Notifications\BarterRequestResponded($barter, 'payment_confirmed')
                    );
                } catch (\Exception $e) {
                    // Abaikan error notifikasi
                }

                Log::info("Barter #{$barter->id} synced: payment_pending → payment_confirmed");
        }

            // Jika expired atau cancel → reset snap token agar bisa generate ulang
            if (in_array($mtStatus, ['expire', 'cancel', 'deny'])) {
                $barter->update(['midtrans_snap_token' => null]);
                Log::info("Barter #{$barter->id} payment expired/cancelled");
            }

        } catch (\Exception $e) {
            // Abaikan error — gunakan status lokal
            Log::warning("Barter sync failed for #{$barter->id}: " . $e->getMessage());
        }
    }

    private function generateBarterSnapToken(BarterRequest $barter, float $amount): ?array
    {
        try {
            $this->initMidtrans();

            $orderId = 'BARTER-' . $barter->id . '-' . substr(md5($barter->id . now()->format('Ymd')), 0, 6);
            $buyer   = $barter->buyer;

            $params = [
                'transaction_details' => [
                    'order_id'     => $orderId,
                    'gross_amount' => (int) $amount,
                ],
                'item_details' => [[
                    'id'       => 'BARTER_DIFF',
                    'price'    => (int) $amount,
                    'quantity' => 1,
                    'name'     => 'Biaya Selisih Tukar Tambah #' . $barter->id,
                ]],
                'customer_details' => [
                    'first_name' => $buyer->name,
                    'email'      => $buyer->email,
                    'phone'      => $buyer->phone,
                ],
                'expiry' => [
                    'start_time' => now()->format('Y-m-d H:i:s O'),
                    'unit'       => 'hours',
                    'duration'   => 24,
                ],
            ];

            return [
                'snap_token'  => \Midtrans\Snap::getSnapToken($params),
                'redirect_url'=> \Midtrans\Snap::getSnapUrl($params),
                'order_id'    => $orderId,
            ];

        } catch (\Exception $e) {
            Log::error('Barter Midtrans error: ' . $e->getMessage());
            return null;
        }
    }
}
