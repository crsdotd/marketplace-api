<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
{
    /**
     * POST /api/v1/ratings
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|exists:transactions,id',
            'type'           => 'required|in:buyer_to_seller,seller_to_buyer',
            'rating'         => 'required|integer|min:1|max:5',
            'review'         => 'sometimes|string|max:1000',
            'images'         => 'sometimes|array|max:3',
            'images.*'       => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::findOrFail($request->transaction_id);
        $user        = $request->user();

        // Validasi hak rating
        if ($request->type === 'buyer_to_seller' && !$transaction->isBuyer($user)) {
            return response()->json(['success' => false, 'message' => 'Hanya pembeli yang bisa memberi rating ke penjual.'], 403);
        }

        if ($request->type === 'seller_to_buyer' && !$transaction->isSeller($user)) {
            return response()->json(['success' => false, 'message' => 'Hanya penjual yang bisa memberi rating ke pembeli.'], 403);
        }

        if ($transaction->status !== 'completed') {
            return response()->json(['success' => false, 'message' => 'Transaksi belum selesai.'], 422);
        }

        // Cek duplikat rating
        $exists = Rating::where('transaction_id', $transaction->id)
            ->where('rater_id', $user->id)
            ->where('type', $request->type)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memberikan rating untuk transaksi ini.'], 422);
        }

        // Upload foto
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = $img->store('ratings', 'public');
            }
        }

        $ratedId = $request->type === 'buyer_to_seller'
            ? $transaction->seller_id
            : $transaction->buyer_id;

        DB::beginTransaction();
        try {
            $rating = Rating::create([
                'transaction_id' => $transaction->id,
                'rater_id'       => $user->id,
                'rated_id'       => $ratedId,
                'product_id'     => $transaction->product_id,
                'type'           => $request->type,
                'rating'         => $request->rating,
                'review'         => $request->review,
                'images'         => $images,
            ]);

            // Update rata-rata rating seller
            if ($request->type === 'buyer_to_seller') {
                $avg   = Rating::where('rated_id', $ratedId)->where('type', 'buyer_to_seller')->avg('rating');
                $count = Rating::where('rated_id', $ratedId)->where('type', 'buyer_to_seller')->count();

                SellerProfile::where('user_id', $ratedId)->update([
                    'rating_avg'   => round($avg, 2),
                    'rating_count' => $count,
                ]);

                // Update rating produk
                $productAvg   = Rating::where('product_id', $transaction->product_id)->avg('rating');
                $productCount = Rating::where('product_id', $transaction->product_id)->count();
                $transaction->product->update([
                    'rating_avg'   => round($productAvg, 2),
                    'rating_count' => $productCount,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rating berhasil diberikan.',
                'data'    => $rating->load('rater'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan rating.'], 500);
        }
    }

    /**
     * GET /api/v1/ratings/seller/{userId}
     */
    public function sellerRatings(Request $request, int $userId): JsonResponse
    {
        $ratings = Rating::with(['rater', 'product.images'])
            ->where('rated_id', $userId)
            ->where('type', 'buyer_to_seller')
            ->orderByDesc('created_at')
            ->paginate(15);

        $summary = [
            'avg'   => round(Rating::where('rated_id', $userId)->where('type', 'buyer_to_seller')->avg('rating') ?? 0, 2),
            'count' => Rating::where('rated_id', $userId)->where('type', 'buyer_to_seller')->count(),
            'breakdown' => Rating::where('rated_id', $userId)
                ->where('type', 'buyer_to_seller')
                ->selectRaw('rating, count(*) as total')
                ->groupBy('rating')
                ->pluck('total', 'rating'),
        ];

        return response()->json(['success' => true, 'summary' => $summary, 'data' => $ratings]);
    }

    /**
     * GET /api/v1/ratings/product/{productId}
     */
    public function productRatings(int $productId): JsonResponse
    {
        $ratings = Rating::with(['rater'])
            ->where('product_id', $productId)
            ->where('type', 'buyer_to_seller')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $ratings]);
    }
}
