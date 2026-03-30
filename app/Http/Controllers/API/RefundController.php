<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\TransactionTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RefundController extends Controller
{
    /**
     * GET /api/v1/refunds
     */
    public function index(Request $request): JsonResponse
    {
        $refunds = Refund::with(['transaction.product.images'])
            ->where('buyer_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $refunds]);
    }

    /**
     * POST /api/v1/refunds
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
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existingRefund) {
            return response()->json(['success' => false, 'message' => 'Refund sudah diajukan sebelumnya.'], 422);
        }

        $images = [];
        foreach ($request->file('evidence_images') as $img) {
            $images[] = $img->store('refund-evidences', 'public');
        }

        DB::beginTransaction();
        try {
            $refund = Refund::create([
                'transaction_id' => $transaction->id,
                'buyer_id'       => $request->user()->id,
                'reason'         => $request->reason,
                'description'    => $request->description,
                'evidence_images'=> $images,
                'refund_amount'  => $transaction->total_price,
                'refund_bank'    => $request->refund_bank,
                'refund_account' => $request->refund_account,
                'refund_holder'  => $request->refund_holder,
            ]);

            $transaction->update(['status' => 'refund_requested']);

            TransactionTimeline::create([
                'transaction_id' => $transaction->id,
                'status'         => 'refund_requested',
                'description'    => "Pembeli mengajukan refund: {$request->reason}",
                'created_by'     => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan refund berhasil dikirim. Tim kami akan memproses dalam 1–3 hari kerja.',
                'data'    => $refund,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mengajukan refund.'], 500);
        }
    }

    /**
     * GET /api/v1/refunds/{id}
     */
    public function show(Request $request, Refund $refund): JsonResponse
    {
        if ($refund->buyer_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $refund->load(['transaction.product.images', 'transaction.seller']),
        ]);
    }
}
