<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * POST /api/v1/register
     * Register hanya butuh data dasar — tidak ada pilihan role
     * Semua user otomatis jadi buyer, bisa aktifkan seller nanti
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users',
            'phone'     => 'required|string|max:20|unique:users',
            'password'  => ['required', 'confirmed', Password::min(8)],
            'wa_number' => 'sometimes|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'phone'     => $request->phone,
                'password'  => Hash::make($request->password),
                'wa_number' => $request->wa_number ?? $request->phone,
                'is_buyer'  => true,   // otomatis bisa beli
                'is_seller' => true,  // seller diaktifkan nanti
                'is_admin'  => false,
            ]);

            // Buat profil dasar
            UserProfile::create(['user_id' => $user->id]);

            DB::commit();

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->refresh();
            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil. Selamat datang!',
                'data'    => [
                    'user'  => $user->load(['profile', 'sellerProfile']),
                    'token' => $token,
                    'roles' => $user->roles,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal registrasi.'], 500);
        }
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
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
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
                'message' => 'Akun Anda telah dinonaktifkan.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $updateData = [];

            // otomatis aktifkan seller saat login
            if (!$user->is_seller) {
                $updateData['is_seller'] = true;
            }

            if ($request->filled('fcm_token')) {
                $updateData['fcm_token'] = $request->fcm_token;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
                $user->refresh();
            }

            // pastikan profile user ada
            if (!$user->profile) {
                UserProfile::create([
                    'user_id' => $user->id,
                ]);
            }

            // pastikan seller profile ada kalau seller aktif
            if ($user->is_seller && !$user->sellerProfile) {
                SellerProfile::create([
                    'user_id' => $user->id,
                    'shop_name' => $user->name . ' Shop',
                    'shop_description' => '',
                ]);
            }

            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data'    => [
                    'user'           => $user->fresh()->load(['profile', 'sellerProfile']),
                    'token'          => $token,
                    'roles'          => $user->fresh()->roles,
                    'is_seller'      => true,
                    'seller_profile' => $user->fresh()->sellerProfile,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/seller/activate
     * User yang sudah login memilih untuk mengaktifkan mode seller
     * Dipanggil saat user klik "Mulai Berjualan" di aplikasi
     */
    public function activateSeller(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cek jika sudah seller
        if ($user->is_seller) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda sudah aktif sebagai seller.',
                'data'    => $user->load('sellerProfile'),
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'shop_name'        => 'required|string|max:255',
            'shop_description' => 'sometimes|string|max:1000',
            'wa_number'        => 'sometimes|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Aktifkan role seller
            $user->update([
                'is_seller' => true,
                'wa_number' => $request->wa_number ?? $user->wa_number,
            ]);

            // Buat seller profile
            SellerProfile::create([
                'user_id'          => $user->id,
                'shop_name'        => $request->shop_name,
                'shop_description' => $request->shop_description,
            ]);

            DB::commit();

            $user->save();
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Mode seller berhasil diaktifkan! Sekarang Anda bisa mulai berjualan.',
                'data'    => $user->fresh()->load(['profile', 'sellerProfile']),
                'roles'   => $user->fresh()->roles,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mengaktifkan mode seller.'], 500);
        }
    }

    /**
     * PUT /api/v1/seller/profile
     * Update profil toko (hanya untuk yang sudah jadi seller)
     */
    public function updateSellerProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSeller()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum mengaktifkan mode seller.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'shop_name'        => 'sometimes|string|max:255',
            'shop_description' => 'sometimes|string|max:1000',
            'shop_banner'      => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('shop_banner')) {
            $path = $request->file('shop_banner')->store('shop-banners', 'public');
            $user->sellerProfile->update(['shop_banner' => $path]);
        }

        $user->sellerProfile->update($request->only(['shop_name', 'shop_description']));

        return response()->json([
            'success' => true,
            'message' => 'Profil toko berhasil diperbarui.',
            'data'    => $user->sellerProfile->fresh(),
        ]);
    }

    /**
     * POST /api/v1/seller/deactivate
     * Nonaktifkan mode seller (opsional)
     */
    public function deactivateSeller(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSeller()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda bukan seller.',
            ], 422);
        }

        // Cek apakah ada transaksi aktif sebagai seller
        $activeTransactions = $user->sellerTransactions()
            ->whereNotIn('status', ['completed', 'cancelled', 'refunded', 'cod_completed'])
            ->count();

        if ($activeTransactions > 0) {
            return response()->json([
                'success' => false,
                'message' => "Tidak bisa menonaktifkan mode seller. Masih ada {$activeTransactions} transaksi aktif.",
            ], 422);
        }

        $user->update(['is_seller' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Mode seller berhasil dinonaktifkan. Anda masih bisa berbelanja sebagai buyer.',
            'roles'   => $user->fresh()->roles,
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
        $user = $request->user()->load(['profile', 'sellerProfile']);
        return response()->json([
            'success' => true,
            'data'    => array_merge($user->toArray(), [
                'roles'     => $user->roles,
                'is_seller' => $user->is_seller,
                'is_buyer'  => $user->is_buyer,
            ]),
        ]);
    }

    /**
     * PUT /api/v1/me
     * Update profil user termasuk social media
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'      => 'sometimes|nullable|string|max:255',
            'wa_number' => 'sometimes|nullable|string|max:20',
            'avatar'    => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:2048',

            'bio'       => 'sometimes|nullable|string|max:500',
            'address'   => 'sometimes|nullable|string',
            'city'      => 'sometimes|nullable|string|max:100',
            'province'  => 'sometimes|nullable|string|max:100',
            'latitude'  => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',

            'social_media'        => 'sometimes|array|max:10',
            'social_media.*.name' => 'required_with:social_media|string|max:50',
            'social_media.*.url'  => 'required_with:social_media|string|url|max:255',

            'shop_name'        => 'sometimes|nullable|string|max:255',
            'shop_description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->update(['avatar' => $path]);
            }

            $userData = $request->only(['name', 'wa_number']);
            if (!empty($userData)) {
                $user->update($userData);
            }

            $profileData = $request->only([
                'bio',
                'address',
                'city',
                'province',
                'latitude',
                'longitude',
            ]);

            if ($request->has('social_media')) {
                $profileData['social_media'] = $request->social_media;
            }

            $profile = $user->profile;
            if (!$profile) {
                $profile = UserProfile::create([
                    'user_id' => $user->id,
                ]);
            }

            if (!empty($profileData)) {
                $profile->update($profileData);
            }

            if ($user->isSeller()) {
                $sellerProfile = $user->sellerProfile;

                if (!$sellerProfile) {
                    $sellerProfile = SellerProfile::create([
                        'user_id' => $user->id,
                        'shop_name' => $request->shop_name ?? '',
                        'shop_description' => $request->shop_description ?? '',
                    ]);
                }

                $sellerData = $request->only(['shop_name', 'shop_description']);
                if (!empty($sellerData)) {
                    $sellerProfile->update($sellerData);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profil berhasil diperbarui.',
                'data'    => $user->fresh()->load(['profile', 'sellerProfile']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
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

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['success' => false, 'message' => 'Password saat ini salah.'], 422);
        }

        $request->user()->update(['password' => Hash::make($request->new_password)]);
        $request->user()->tokens()->delete();

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah. Silakan login ulang.']);
    }
}
