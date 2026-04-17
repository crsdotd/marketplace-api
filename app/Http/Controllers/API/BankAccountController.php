<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends Controller
{
    // GET /api/v1/bank-accounts/my
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user()->bankAccounts()->orderByDesc('is_default')->get(),
        ]);
    }

    // POST /api/v1/bank-accounts/my
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_name'      => 'required|string|max:100',
            'account_number' => 'required|string|max:30',
            'account_holder' => 'required|string|max:255',
            'is_default'     => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Jika set default, unset default yang lama
        if ($request->is_default) {
            $user->bankAccounts()->update(['is_default' => false]);
        }

        // Jika belum punya rekening sama sekali, otomatis jadi default
        $isDefault = $request->is_default
            || $user->bankAccounts()->count() === 0;

        $bank = $user->bankAccounts()->create([
            'bank_name'      => $request->bank_name,
            'account_number' => $request->account_number,
            'account_holder' => $request->account_holder,
            'is_default'     => $isDefault,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rekening berhasil ditambahkan.',
            'data'    => $bank,
        ], 201);
    }

    // PUT /api/v1/bank-accounts/my/{id}/set-default
    public function setDefault(Request $request, UserBankAccount $bankAccount): JsonResponse
    {
        if ($bankAccount->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $request->user()->bankAccounts()->update(['is_default' => false]);
        $bankAccount->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Rekening default berhasil diubah.',
            'data'    => $bankAccount->fresh(),
        ]);
    }

    // DELETE /api/v1/bank-accounts/my/{id}
    public function destroy(Request $request, UserBankAccount $bankAccount): JsonResponse
    {
        if ($bankAccount->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $wasDefault = $bankAccount->is_default;
        $bankAccount->delete();

        // Jika yang dihapus adalah default, set rekening pertama sebagai default baru
        if ($wasDefault) {
            $request->user()->bankAccounts()->first()?->update(['is_default' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rekening berhasil dihapus.',
        ]);
    }
}