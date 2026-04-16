<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        if ($keyword = $request->search) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'LIKE', "%{$keyword}%")
                  ->orWhere('description', 'LIKE', "%{$keyword}%");
            });
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->condition) {
            $query->where('condition', $request->condition);
        }

        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->transaction_type) {
            $query->where(function ($q) use ($request) {
                $q->where('transaction_type', $request->transaction_type)
                  ->orWhere('transaction_type', 'both');
            });
        }

        if ($request->latitude && $request->longitude) {
            $query->nearby(
                (float) $request->latitude,
                (float) $request->longitude,
                (int) ($request->radius ?? 50)
            );
        }

        if ($request->city) {
            $query->where('location_city', 'LIKE', "%{$request->city}%");
        }

        if (!$request->latitude) {
            $query->orderByDesc('is_promoted');
        }

        match ($request->sort) {
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
            'latitude'          => 'sometimes|numeric',
            'longitude'         => 'sometimes|numeric',
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
                    $product->tags()->create(['tag' => strtolower(trim($tag))]);
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
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan produk: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/v1/products/{id}
     * Fix: proses setiap field secara eksplisit — termasuk images dan tags
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
            'latitude'          => 'sometimes|nullable|numeric',
            'longitude'         => 'sometimes|nullable|numeric',
            'status'            => 'sometimes|in:active,inactive',
            // Gambar baru (opsional — akan ditambahkan ke gambar yang sudah ada)
            'images'            => 'sometimes|array|max:5',
            'images.*'          => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            // Hapus gambar tertentu by id
            'delete_image_ids'  => 'sometimes|array',
            'delete_image_ids.*'=> 'integer|exists:product_images,id',
            // Replace semua tags
            'tags'              => 'sometimes|array|max:10',
            'tags.*'            => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // ── Update field teks ──────────────────────────────────
            $textFields = [
                'category_id', 'title', 'description', 'price', 'stock',
                'condition', 'transaction_type', 'location_city',
                'location_province', 'latitude', 'longitude', 'status',
            ];

            $updateData = [];
            foreach ($textFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            if (!empty($updateData)) {
                // Update slug jika title berubah
                if (isset($updateData['title'])) {
                    $updateData['slug'] = Str::slug($updateData['title']) . '-' . Str::random(6);
                }
                $product->update($updateData);
            }

            // ── Hapus gambar tertentu (jika diminta) ───────────────
            if ($request->has('delete_image_ids') && !empty($request->delete_image_ids)) {
                $imagesToDelete = ProductImage::whereIn('id', $request->delete_image_ids)
                    ->where('product_id', $product->id)
                    ->get();

                foreach ($imagesToDelete as $img) {
                    Storage::disk('public')->delete($img->image_path);
                    $img->delete();
                }

                // Set ulang primary jika yang dihapus adalah primary
                $remaining = $product->images()->orderBy('sort_order')->first();
                if ($remaining && !$product->images()->where('is_primary', true)->exists()) {
                    $remaining->update(['is_primary' => true]);
                }
            }

            // ── Upload gambar baru (tambahan) ──────────────────────
            if ($request->hasFile('images')) {
                $currentCount = $product->images()->count();
                $newImages    = $request->file('images');
                $totalAfter   = $currentCount + count($newImages);

                if ($totalAfter > 5) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Total gambar tidak boleh lebih dari 5. Saat ini ada {$currentCount} gambar, kamu menambah " . count($newImages) . ".",
                    ], 422);
                }

                $lastOrder = $product->images()->max('sort_order') ?? -1;

                foreach ($newImages as $index => $image) {
                    $path = $image->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $currentCount === 0 && $index === 0,
                        'sort_order' => $lastOrder + $index + 1,
                    ]);
                }
            }

            // ── Update tags (replace semua) ────────────────────────
            if ($request->has('tags')) {
                // Hapus semua tag lama
                $product->tags()->delete();

                // Simpan tag baru
                if (!empty($request->tags)) {
                    foreach ($request->tags as $tag) {
                        if (!empty(trim($tag))) {
                            $product->tags()->create(['tag' => strtolower(trim($tag))]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diperbarui.',
                'data'    => $product->fresh()->load(['images', 'category', 'tags']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui produk: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/products/{id}
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        // Hapus semua gambar dari storage
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $product->delete();
        return response()->json(['success' => true, 'message' => 'Produk berhasil dihapus.']);
    }

    /**
     * GET /api/v1/my/products
     */
    public function myProducts(Request $request): JsonResponse
    {
        $products = Product::with(['images', 'category', 'tags'])
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
