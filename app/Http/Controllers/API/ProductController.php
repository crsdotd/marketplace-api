<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['images', 'category', 'seller.sellerProfile'])
            ->active();

        // Pencarian keyword
        if ($keyword = $request->search) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'LIKE', "%{$keyword}%")
                  ->orWhere('description', 'LIKE', "%{$keyword}%");
            });
        }

        // Filter kategori
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Filter kondisi
        if ($request->condition) {
            $query->where('condition', $request->condition);
        }

        // Filter harga
        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }

        // Filter jenis transaksi
        if ($request->transaction_type) {
            $query->where(function ($q) use ($request) {
                $q->where('transaction_type', $request->transaction_type)
                  ->orWhere('transaction_type', 'both');
            });
        }

        // Filter lokasi berdasarkan radius (Haversine)
        if ($request->latitude && $request->longitude) {
            $query->nearby(
                (float)$request->latitude,
                (float)$request->longitude,
                (int)($request->radius ?? 50)
            );
        }

        // Filter kota
        if ($request->city) {
            $query->where('location_city', 'LIKE', "%{$request->city}%");
        }

        // Promoted products tampil duluan
        if (!$request->latitude) {
            $query->orderByDesc('is_promoted');
        }

        // Sorting
        match($request->sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'newest'     => $query->orderByDesc('created_at'),
            'popular'    => $query->orderByDesc('view_count'),
            'rating'     => $query->orderByDesc('rating_avg'),
            default      => $query->orderByDesc('created_at'),
        };

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->per_page ?? 15),
        ]);
    }

    /**
     * GET /api/v1/products/{id}
     */
    public function show(Product $product): JsonResponse
    {
        $product->increment('view_count');

        $product->load([
            'images', 'category', 'tags',
            'seller.sellerProfile',
            'seller.profile',
            'ratings.rater',
        ]);

        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * POST /api/v1/products
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isSeller()) {
            return response()->json(['success' => false, 'message' => 'Hanya seller yang bisa menambah produk.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id'       => 'required|exists:categories,id',
            'title'             => 'required|string|max:255',
            'description'       => 'required|string',
            'price'             => 'required|numeric|min:0',
            'stock'             => 'required|integer|min:1',
            'condition'         => 'required|in:new,used',
            'transaction_type'  => 'required|in:cod,rekber,both',
            'location_city'     => 'required|string|max:100',
            'location_province' => 'required|string|max:100',
            'latitude'          => 'sometimes|numeric|between:-90,90',
            'longitude'         => 'sometimes|numeric|between:-180,180',
            'images'            => 'required|array|min:1|max:5',
            'images.*'          => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'tags'              => 'sometimes|array|max:10',
            'tags.*'            => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $product = Product::create([
                'user_id'           => $request->user()->id,
                'category_id'       => $request->category_id,
                'title'             => $request->title,
                'slug'              => Str::slug($request->title) . '-' . Str::random(6),
                'description'       => $request->description,
                'price'             => $request->price,
                'stock'             => $request->stock,
                'condition'         => $request->condition,
                'transaction_type'  => $request->transaction_type,
                'location_city'     => $request->location_city,
                'location_province' => $request->location_province,
                'latitude'          => $request->latitude,
                'longitude'         => $request->longitude,
            ]);

            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }

            if ($request->tags) {
                foreach ($request->tags as $tag) {
                    $product->tags()->create(['tag' => strtolower($tag)]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil ditambahkan.',
                'data'    => $product->load(['images', 'category', 'tags']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan produk.'], 500);
        }
    }

    /**
     * PUT /api/v1/products/{id}
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id'       => 'sometimes|exists:categories,id',
            'title'             => 'sometimes|string|max:255',
            'description'       => 'sometimes|string',
            'price'             => 'sometimes|numeric|min:0',
            'stock'             => 'sometimes|integer|min:0',
            'condition'         => 'sometimes|in:new,used',
            'transaction_type'  => 'sometimes|in:cod,rekber,both',
            'location_city'     => 'sometimes|string|max:100',
            'location_province' => 'sometimes|string|max:100',
            'status'            => 'sometimes|in:active,inactive',
            'latitude'          => 'sometimes|numeric',
            'longitude'         => 'sometimes|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $product->update($request->only([
            'category_id', 'title', 'description', 'price', 'stock',
            'condition', 'transaction_type', 'location_city', 'location_province',
            'latitude', 'longitude', 'status',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui.',
            'data'    => $product->fresh()->load(['images', 'category', 'tags']),
        ]);
    }

    /**
     * DELETE /api/v1/products/{id}
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $product->delete();
        return response()->json(['success' => true, 'message' => 'Produk berhasil dihapus.']);
    }

    /**
     * GET /api/v1/my/products
     */
    public function myProducts(Request $request): JsonResponse
    {
        $products = Product::with(['images', 'category'])
            ->where('user_id', $request->user()->id)
            ->withCount('favorites')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * POST /api/v1/products/{id}/favorite
     */
    public function toggleFavorite(Request $request, Product $product): JsonResponse
    {
        $user     = $request->user();
        $favorite = $user->favorites()->where('product_id', $product->id)->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['success' => true, 'message' => 'Dihapus dari favorit.', 'is_favorite' => false]);
        }

        $user->favorites()->create(['product_id' => $product->id]);
        return response()->json(['success' => true, 'message' => 'Ditambahkan ke favorit.', 'is_favorite' => true]);
    }

    /**
     * GET /api/v1/my/favorites
     */
    public function favorites(Request $request): JsonResponse
    {
        $favorites = $request->user()
            ->favorites()
            ->with(['product.images', 'product.category', 'product.seller.sellerProfile'])
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $favorites]);
    }
}
