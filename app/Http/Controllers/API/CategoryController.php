<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * GET /api/v1/categories
     */
    public function index(): JsonResponse
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * GET /api/v1/categories/{id}/products
     */
    public function products(Request $request, Category $category): JsonResponse
    {
        // Ambil semua sub-kategori juga
        $categoryIds = collect([$category->id])
            ->merge($category->children->pluck('id'));

        $products = \App\Models\Product::with(['images', 'seller.sellerProfile'])
            ->whereIn('category_id', $categoryIds)
            ->active()
            ->orderByDesc('is_promoted')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success'  => true,
            'category' => $category,
            'data'     => $products,
        ]);
    }
}
