<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * POST /api/v1/register
     */
    public function register(Request $request): JsonResponse
    {
        $rules = [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users',
            'phone'     => 'required|string|max:20|unique:users',
            'password'  => ['required', 'confirmed', Password::min(8)],
            'role'      => 'sometimes|in:buyer,seller',
            'wa_number' => 'sometimes|string|max:20',
            'shop_name' => 'required_if:role,seller|string|max:255',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'password'  => Hash::make($request->password),
            'role'      => $request->role ?? 'buyer',
            'wa_number' => $request->wa_number ?? $request->phone,
        ]);

        UserProfile::create(['user_id' => $user->id]);

        if ($user->role === 'seller') {
            SellerProfile::create([
                'user_id'   => $user->id,
                'shop_name' => $request->shop_name,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil.',
            'data'    => [
                'user'  => $user->load(['profile', 'sellerProfile']),
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'     => 'required|email',
            'password'  => 'required',
            'fcm_token' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi admin.',
            ], 403);
        }

        if ($request->fcm_token) {
            $user->update(['fcm_token' => $request->fcm_token]);
        }

        // Hapus token lama jika ada
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'user'  => $user->load(['profile', 'sellerProfile']),
                'token' => $token,
            ],
        ]);
    }

    /**
     * POST /api/v1/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logout berhasil.']);
    }

    /**
     * GET /api/v1/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user()->load(['profile', 'sellerProfile']),
        ]);
    }

    /**
     * PUT /api/v1/me
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'      => 'sometimes|string|max:255',
            'wa_number' => 'sometimes|string|max:20',
            'avatar'    => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:2048',
            'bio'       => 'sometimes|string|max:500',
            'address'   => 'sometimes|string',
            'city'      => 'sometimes|string|max:100',
            'province'  => 'sometimes|string|max:100',
            'latitude'  => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            // Seller
            'shop_name'        => 'sometimes|string|max:255',
            'shop_description' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => $path]);
        }

        $user->update($request->only(['name', 'wa_number']));
        $user->profile->update($request->only(['bio', 'address', 'city', 'province', 'latitude', 'longitude']));

        if ($user->isSeller() && $user->sellerProfile) {
            $user->sellerProfile->update($request->only(['shop_name', 'shop_description']));
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $user->fresh()->load(['profile', 'sellerProfile']),
        ]);
    }

    /**
     * POST /api/v1/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password'     => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Password saat ini salah.'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah. Silakan login ulang.']);
    }
}
