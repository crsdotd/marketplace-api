<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdPackage;
use App\Models\ProductAd;
use App\Models\AdPayment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdController extends Controller
{
    /**
     * GET /api/v1/ads/packages
     */
    public function packages(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => AdPackage::where('is_active', true)->orderBy('price')->get(),
        ]);
    }

    /**
     * POST /api/v1/ads
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id'    => 'required|exists:products,id',
            'ad_package_id' => 'required|exists:ad_packages,id',
            'bank_name'     => 'required|string|max:100',
            'bank_account'  => 'required|string|max:30',
            'proof_image'   => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($request->product_id);

        if ($product->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Produk bukan milik Anda.'], 403);
        }

        $package   = AdPackage::findOrFail($request->ad_package_id);
        $proofPath = $request->file('proof_image')->store('ad-payments', 'public');

        $productAd = ProductAd::create([
            'product_id'    => $product->id,
            'user_id'       => $request->user()->id,
            'ad_package_id' => $package->id,
            'status'        => 'pending_payment',
        ]);

        AdPayment::create([
            'product_ad_id' => $productAd->id,
            'user_id'       => $request->user()->id,
            'amount'        => $package->price,
            'bank_name'     => $request->bank_name,
            'bank_account'  => $request->bank_account,
            'proof_image'   => $proofPath,
            'paid_at'       => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permohonan iklan dikirim. Iklan akan aktif setelah pembayaran diverifikasi oleh admin.',
            'data'    => $productAd->load(['adPackage', 'product.images', 'payment']),
        ], 201);
    }

    /**
     * GET /api/v1/ads/my
     */
    public function myAds(Request $request): JsonResponse
    {
        $ads = ProductAd::with(['product.images', 'adPackage', 'payment'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $ads]);
    }
}
