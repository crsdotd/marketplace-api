<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\TransactionTimeline;
use App\Models\PlatformBankAccount;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * GET /api/v1/transactions
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $role  = $request->role ?? 'buyer';

        $query = Transaction::with(['product.images', 'buyer', 'seller.sellerProfile'])
            ->orderByDesc('created_at');

        $role === 'buyer'
            ? $query->where('buyer_id', $user->id)
            : $query->where('seller_id', $user->id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json(['success' => true, 'data' => $query->paginate(15)]);
    }

    /**
     * POST /api/v1/transactions
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id'       => 'required|exists:products,id',
            'quantity'         => 'required|integer|min:1',
            'type'             => 'required|in:cod,rekber',
            'shipping_address' => 'required_if:type,rekber|string',
            'shipping_city'    => 'required_if:type,rekber|string',
            'notes'            => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($request->product_id);

        if ($product->user_id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa membeli produk sendiri.'], 422);
        }

        if ($product->stock < $request->quantity) {
            return response()->json(['success' => false, 'message' => 'Stok tidak mencukupi.'], 422);
        }

        if ($product->transaction_type !== 'both' && $product->transaction_type !== $request->type) {
            return response()->json([
                'success' => false,
                'message' => "Produk ini hanya mendukung transaksi {$product->transaction_type}.",
            ], 422);
        }

        DB::beginTransaction();
        try {
            $feePercent   = (float) env('PLATFORM_FEE_PERCENT', 3);
            $totalPrice   = $product->price * $request->quantity;
            $platformFee  = $request->type === 'rekber' ? ($totalPrice * $feePercent / 100) : 0;
            $finalAmount  = $totalPrice + $platformFee;

            $transaction = Transaction::create([
                'transaction_code' => 'TRX-' . Carbon::now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'buyer_id'         => $request->user()->id,
                'seller_id'        => $product->user_id,
                'product_id'       => $product->id,
                'quantity'         => $request->quantity,
                'price'            => $product->price,
                'total_price'      => $totalPrice,
                'shipping_cost'    => 0,
                'platform_fee'     => $platformFee,
                'final_amount'     => $finalAmount,
                'type'             => $request->type,
                'status'           => $request->type === 'cod' ? 'cod_waiting' : 'pending',
                'shipping_address' => $request->shipping_address,
                'shipping_city'    => $request->shipping_city,
                'notes'            => $request->notes,
                'payment_deadline' => Carbon::now()->addHours(24),
                'auto_complete_at' => Carbon::now()->addDays((int) env('AUTO_COMPLETE_DAYS', 7)),
            ]);

            $product->decrement('stock');

            TransactionTimeline::create([
                'transaction_id' => $transaction->id,
                'status'         => $transaction->status,
                'description'    => 'Transaksi berhasil dibuat.',
                'created_by'     => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibuat.',
                'data'    => $transaction->load(['product.images', 'buyer', 'seller.sellerProfile']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal membuat transaksi.'], 500);
        }
    }

    /**
     * GET /api/v1/transactions/{id}
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        if (!$transaction->involves($request->user())) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $transaction->load([
                'product.images', 'buyer.profile',
                'seller.sellerProfile', 'payment',
                'timelines.actor', 'refund',
                'meetingPoints', 'ratings',
            ]),
        ]);
    }

    /**
     * POST /api/v1/transactions/{id}/payment
     */
    // public function uploadPayment(Request $request, Transaction $transaction): JsonResponse
    // {
    //     if (!$transaction->isBuyer($request->user())) {
    //         return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
    //     }

    //     if ($transaction->type !== 'rekber') {
    //         return response()->json(['success' => false, 'message' => 'Hanya untuk transaksi rekber.'], 422);
    //     }

    //     if ($transaction->status !== 'pending') {
    //         return response()->json(['success' => false, 'message' => 'Status transaksi tidak valid.'], 422);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'bank_name'    => 'required|string',
    //         'bank_account' => 'required|string',
    //         'bank_holder'  => 'required|string',
    //         'proof_image'  => 'required|image|mimes:jpg,jpeg,png|max:5120',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
    //     }

    //     $path = $request->file('proof_image')->store('payment-proofs', 'public');

    //     DB::beginTransaction();
    //     try {
    //         TransactionPayment::create([
    //             'transaction_id' => $transaction->id,
    //             'method'         => 'bank_transfer',
    //             'bank_name'      => $request->bank_name,
    //             'bank_account'   => $request->bank_account,
    //             'bank_holder'    => $request->bank_holder,
    //             'amount'         => $transaction->final_amount,
    //             'proof_image'    => $path,
    //             'status'         => 'pending',
    //             'paid_at'        => now(),
    //         ]);

    //         $transaction->update(['status' => 'payment_confirmed']);

    //         TransactionTimeline::create([
    //             'transaction_id' => $transaction->id,
    //             'status'         => 'payment_confirmed',
    //             'description'    => 'Bukti pembayaran diunggah. Menunggu verifikasi admin.',
    //             'created_by'     => $request->user()->id,
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Bukti transfer berhasil dikirim, menunggu verifikasi.',
    //             'data'    => $transaction->fresh()->load('payment'),
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['success' => false, 'message' => 'Gagal mengunggah bukti.'], 500);
    //     }
    // }

    /**
     * PUT /api/v1/transactions/{id}/status
     * Digunakan seller untuk update: processing, shipped
     */
    public function updateStatus(Request $request, Transaction $transaction): JsonResponse
    {
        if (!$transaction->isSeller($request->user())) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status'          => 'required|in:processing,shipped,cod_completed',
            'description'     => 'sometimes|string',
            'tracking_number' => 'required_if:status,shipped|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $updates = ['status' => $request->status];
        if ($request->tracking_number) {
            $updates['tracking_number'] = $request->tracking_number;
        }

        $transaction->update($updates);

        TransactionTimeline::create([
            'transaction_id' => $transaction->id,
            'status'         => $request->status,
            'description'    => $request->description ?? match($request->status) {
                'processing'    => 'Penjual sedang memproses pesanan.',
                'shipped'       => 'Barang telah dikirim. No. Resi: ' . ($request->tracking_number ?? '-'),
                'cod_completed' => 'Transaksi COD selesai dilakukan.',
                default         => 'Status diperbarui.',
            },
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status transaksi berhasil diperbarui.',
            'data'    => $transaction->fresh(),
        ]);
    }

    /**
     * POST /api/v1/transactions/{id}/confirm
     * Buyer konfirmasi barang diterima → dana cair ke seller
     */
    public function confirmReceived(Request $request, Transaction $transaction): JsonResponse
{
    if (!$transaction->isBuyer($request->user())) {
        return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
    }

    // ✅ Tambah 'payment_confirmed' dan 'processing' untuk fleksibilitas
    $allowedStatuses = ['shipped', 'delivered', 'processing', 'payment_confirmed'];

    if (!in_array($transaction->status, $allowedStatuses)) {
        return response()->json([
            'success' => false,
            'message' => "Konfirmasi tidak bisa dilakukan. Status saat ini: {$transaction->status}",
            'current_status' => $transaction->status,
            'allowed_statuses' => $allowedStatuses,
        ], 422);
    }

    DB::beginTransaction();
    try {
        $transaction->update(['status' => 'completed']);

        // Cairkan dana ke saldo seller
        $sellerAmount = $transaction->total_price;
        $transaction->seller->increment('balance', $sellerAmount);

        // Update statistik seller
        $transaction->seller->sellerProfile?->increment('total_sold');

        TransactionTimeline::create([
            'transaction_id' => $transaction->id,
            'status'         => 'completed',
            'description'    => 'Pembeli mengkonfirmasi barang diterima. Dana Rp ' .
                                number_format($sellerAmount, 0, ',', '.') .
                                ' dicairkan ke penjual.',
            'created_by'     => $request->user()->id,
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Transaksi selesai. Terima kasih! Jangan lupa beri rating untuk penjual.',
            'data'    => $transaction->fresh(),
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => 'Gagal mengkonfirmasi transaksi.'], 500);
    }
}

    /**
     * GET /api/v1/bank-accounts
     */
    public function platformBankAccounts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => PlatformBankAccount::where('is_active', true)->get(),
        ]);
    }
}
