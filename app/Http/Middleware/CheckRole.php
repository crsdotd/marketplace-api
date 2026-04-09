<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // Cek apakah user punya salah satu role yang diminta
        $hasRole = false;
        foreach ($roles as $role) {
            if ($role === 'seller' && $user->is_seller) { $hasRole = true; break; }
            if ($role === 'buyer'  && $user->is_buyer)  { $hasRole = true; break; }
            if ($role === 'admin'  && $user->is_admin)  { $hasRole = true; break; }
        }

        if (!$hasRole) {
            $needed = implode(' atau ', $roles);
            return response()->json([
                'success' => false,
                'message' => "Akses ditolak. Fitur ini membutuhkan role: {$needed}.",
                'hint'    => in_array('seller', $roles)
                    ? 'Aktifkan mode seller terlebih dahulu via POST /api/v1/seller/activate'
                    : null,
            ], 403);
        }

        return $next($request);
    }
}
