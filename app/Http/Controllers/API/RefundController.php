<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\TransactionTimeline;
use App\Models\User;
use App\Notifications\RefundRequested;
use App\Notifications\RefundResponded;
use App\Notifications\RefundEscalated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RefundController extends Controller
{
    // ═══════════════════════════════════════════════
    //  BUYER — Ajukan refund
    // ═══════════════════════════════════════════════

    /**
     * GET /api/v1/refunds
     * List refund milik buyer
     */
    public function index(Request $request): JsonResponse
    {
        $refunds = Refund::with([
            'transaction.product.images',
            'seller',
        ])
        ->where('buyer_id', $request->user()->id)
        ->orderByDesc('created_at')
        ->paginate(15);

        return response()->json(['success' => true, 'data' => $refunds]);
    }

    /**
     * POST /api/v1/refunds
     * Buyer ajukan refund
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id'    => 'required|exists:transactions,id',
            'reason'            => 'required|string|max:255',
            'description'       => 'sometimes|string|max:2000',
            'evidence_images'   => 'required|array|min:1|max:5',
            'evidence_images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'refund_bank'       => 'required|string|max:100',
            'refund_account'    => 'required|string|max:30',
            'refund_holder'     => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::findOrFail($request->transaction_id);

        if (!$transaction->isBuyer($request->user())) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (!in_array($transaction->status, ['delivered', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Refund hanya bisa diajukan setelah barang diterima.',
            ], 422);
        }

        $existingRefund = Refund::where('transaction_id', $transaction->id)
            ->whereNotIn('status', ['rejected', 'processed'])
            ->exists();

        if ($existingRefund) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah ada permintaan refund aktif untuk transaksi ini.',
            ], 422);
        }

        $images = [];
        foreach ($request->file('evidence_images') as $img) {
            $images[] = $img->store('refund-evidences', 'public');
        }

        DB::beginTransaction();
        try {
            $refund = Refund::create([
                'transaction_id'  => $transaction->id,
                'buyer_id'        => $request->user()->id,
                'seller_id'       => $transaction->seller_id,
                'reason'          => $request->reason,
                'description'     => $request->description,
                'evidence_images' => $images,
                'status'          => 'pending',
                'refund_amount'   => $transaction->total_price,
                'refund_bank'     => $request->refund_bank,
                'refund_account'  => $request->refund_account,
                'refund_holder'   => $request->refund_holder,
            ]);

            $transaction->update(['status' => 'refund_requested']);

            TransactionTimeline::create([
                'transaction_id' => $transaction->id,
                'status'         => 'refund_requested',
                'description'    => "Buyer mengajukan refund: {$request->reason}",
                'created_by'     => $request->user()->id,
            ]);

            // Kirim notifikasi ke SELLER
            $transaction->seller->notify(new RefundRequested($refund));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan refund berhasil dikirim ke penjual. Penjual akan merespons dalam 2x24 jam.',
                'data'    => $refund->load(['transaction.product', 'seller']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mengajukan refund.'], 500);
        }
    }

    /**
     * GET /api/v1/refunds/{id}
     * Detail refund — buyer atau seller bisa akses
     */
    public function show(Request $request, Refund $refund): JsonResponse
    {
        $user = $request->user();

        if ($refund->buyer_id !== $user->id && $refund->seller_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $refund->load([
                'transaction.product.images',
                'buyer', 'seller.sellerProfile',
            ]),
        ]);
    }

    /**
     * POST /api/v1/refunds/{id}/escalate
     * Buyer eskalasi ke admin jika seller menolak
     */
    public function escalate(Request $request, Refund $refund): JsonResponse
    {
        if ($refund->buyer_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if ($refund->status !== 'seller_rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Eskalasi hanya bisa dilakukan jika refund ditolak seller.',
            ], 422);
        }

        if ($refund->is_escalated) {
            return response()->json([
                'success' => false,
                'message' => 'Refund sudah dieskalasi sebelumnya.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $refund->update([
                'status'       => 'admin_reviewing',
                'is_escalated' => true,
                'escalated_at' => now(),
            ]);

            TransactionTimeline::create([
                'transaction_id' => $refund->transaction_id,
                'status'         => 'admin_reviewing',
                'description'    => 'Buyer mengeskalasi refund ke admin karena ditolak seller.',
                'created_by'     => $request->user()->id,
            ]);

            // Kirim notifikasi ke semua admin
            User::where('is_admin', true)->each(function ($admin) use ($refund) {
                $admin->notify(new RefundEscalated($refund));
            });

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund berhasil dieskalasi ke admin. Admin akan meninjau dalam 1-3 hari kerja.',
                'data'    => $refund->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal eskalasi refund.'], 500);
        }
    }

    // ═══════════════════════════════════════════════
    //  SELLER — Tangani permintaan refund
    // ═══════════════════════════════════════════════

    /**
     * GET /api/v1/seller/refunds
     * Daftar refund yang masuk ke seller
     */
    public function sellerIndex(Request $request): JsonResponse
    {
        $query = Refund::with([
            'transaction.product.images',
            'buyer',
        ])
        ->where('seller_id', $request->user()->id)
        ->orderByDesc('created_at');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(15),
        ]);
    }

    /**
     * PUT /api/v1/seller/refunds/{id}/review
     * Seller mulai review refund (ubah status ke reviewing)
     */
    public function sellerReview(Request $request, Refund $refund): JsonResponse
    {
        if ($refund->seller_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if ($refund->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Refund tidak dalam status pending.',
            ], 422);
        }

        $refund->update(['status' => 'seller_reviewing']);

        TransactionTimeline::create([
            'transaction_id' => $refund->transaction_id,
            'status'         => 'seller_reviewing',
            'description'    => 'Seller sedang mereview permintaan refund.',
            'created_by'     => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status refund diperbarui ke reviewing.',
            'data'    => $refund->fresh(),
        ]);
    }

    /**
     * POST /api/v1/seller/refunds/{id}/approve
     * Seller menyetujui refund
     */
    public function sellerApprove(Request $request, Refund $refund): JsonResponse
    {
        if ($refund->seller_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (!in_array($refund->status, ['pending', 'seller_reviewing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Refund tidak bisa disetujui pada status ini.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'seller_note' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $refund->update([
                'status'               => 'seller_approved',
                'seller_note'          => $request->seller_note ?? 'Refund disetujui oleh penjual.',
                'seller_responded_at'  => now(),
            ]);

            TransactionTimeline::create([
                'transaction_id' => $refund->transaction_id,
                'status'         => 'refund_requested',
                'description'    => 'Seller menyetujui permintaan refund. Menunggu proses pengembalian dana.',
                'created_by'     => $request->user()->id,
            ]);

            // Kurangi saldo seller jika dana sudah masuk
            $transaction = $refund->transaction;
            if ($transaction->seller->balance >= $refund->refund_amount) {
                $transaction->seller->decrement('balance', $refund->refund_amount);
            }

            // Kirim notifikasi ke BUYER
            $refund->buyer->notify(new RefundResponded($refund, 'seller_approved'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund berhasil disetujui. Dana akan dikembalikan ke buyer.',
                'data'    => $refund->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyetujui refund.'], 500);
        }
    }

    /**
     * POST /api/v1/seller/refunds/{id}/reject
     * Seller menolak refund — buyer bisa eskalasi ke admin
     */
    public function sellerReject(Request $request, Refund $refund): JsonResponse
    {
        if ($refund->seller_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (!in_array($refund->status, ['pending', 'seller_reviewing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Refund tidak bisa ditolak pada status ini.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'seller_note' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $refund->update([
                'status'              => 'seller_rejected',
                'seller_note'         => $request->seller_note,
                'seller_responded_at' => now(),
            ]);

            TransactionTimeline::create([
                'transaction_id' => $refund->transaction_id,
                'status'         => 'refund_requested',
                'description'    => "Seller menolak refund. Alasan: {$request->seller_note}",
                'created_by'     => $request->user()->id,
            ]);

            // Kirim notifikasi ke BUYER
            $refund->buyer->notify(new RefundResponded($refund, 'seller_rejected'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund ditolak. Silahkan hubungi penjual.',
                'data'    => $refund->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menolak refund.'], 500);
        }
    }
}
