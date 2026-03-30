<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    /**
     * GET /api/v1/wallet/balance
     */
    public function balance(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'balance'          => $request->user()->balance,
                'balance_formatted' => 'Rp ' . number_format($request->user()->balance, 0, ',', '.'),
            ],
        ]);
    }

    /**
     * POST /api/v1/wallet/withdraw
     */
    public function withdraw(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount'       => 'required|numeric|min:10000',
            'bank_name'    => 'required|string|max:100',
            'bank_account' => 'required|string|max:30',
            'bank_holder'  => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ($request->amount > $user->balance) {
            return response()->json([
                'success' => false,
                'message' => 'Saldo tidak mencukupi.',
            ], 422);
        }

        // Kurangi saldo & buat request withdrawal
        $user->decrement('balance', $request->amount);

        $withdrawal = Withdrawal::create([
            'user_id'      => $user->id,
            'amount'       => $request->amount,
            'bank_name'    => $request->bank_name,
            'bank_account' => $request->bank_account,
            'bank_holder'  => $request->bank_holder,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan penarikan saldo berhasil dikirim. Akan diproses dalam 1x24 jam kerja.',
            'data'    => $withdrawal,
        ], 201);
    }

    /**
     * GET /api/v1/wallet/history
     */
    public function history(Request $request): JsonResponse
    {
        $withdrawals = Withdrawal::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        // Riwayat pendapatan dari transaksi selesai
        $incomes = Transaction::where('seller_id', $request->user()->id)
            ->where('status', 'completed')
            ->select('transaction_code', 'total_price as amount', 'created_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'withdrawals' => $withdrawals,
                'incomes'     => $incomes,
                'balance'     => $request->user()->balance,
            ],
        ]);
    }
}
