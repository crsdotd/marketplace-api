<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\TransactionTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Inisialisasi Midtrans Config
     */
    private function initMidtrans(): void
    {
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$clientKey    = config('midtrans.client_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production');
        \Midtrans\Config::$isSanitized  = config('midtrans.is_sanitized');
        \Midtrans\Config::$is3ds        = config('midtrans.is_3ds');
    }

    /**
     * POST /api/v1/payment/create/{transaction}
     * Buat pembayaran Midtrans — generate Snap Token
     * Buyer panggil ini setelah buat transaksi rekber
     */
    public function create(Request $request, Transaction $transaction): JsonResponse
    {
        // Validasi akses
        if (!$transaction->isBuyer($request->user())) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if ($transaction->type !== 'rekber') {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran Midtrans hanya untuk transaksi rekber.',
            ], 422);
        }

        if (!in_array($transaction->status, ['pending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak dalam status pending.',
            ], 422);
        }

        // Cek apakah sudah ada payment pending
        $existingPayment = TransactionPayment::where('transaction_id', $transaction->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment && $existingPayment->midtrans_token) {
            return response()->json([
                'success'      => true,
                'message'      => 'Token pembayaran sudah ada.',
                'data'         => [
                    'snap_token'   => $existingPayment->midtrans_token,
                    'redirect_url' => $existingPayment->midtrans_redirect_url,
                    'order_id'     => $existingPayment->midtrans_order_id,
                    'amount'       => $existingPayment->amount,
                    'expired_at'   => $existingPayment->expired_at,
                ],
            ]);
        }

        $this->initMidtrans();

        $transaction->load(['buyer', 'seller', 'product']);
        $buyer = $transaction->buyer;

        // Buat parameter untuk Midtrans Snap
        $params = [
            'transaction_details' => [
                'order_id'     => $transaction->transaction_code,
                'gross_amount' => (int) $transaction->final_amount,
            ],
            'item_details' => [
                [
                    'id'       => $transaction->product_id,
                    'price'    => (int) $transaction->price,
                    'quantity' => $transaction->quantity,
                    'name'     => substr($transaction->product->title, 0, 50),
                ],
                // Tambahkan platform fee sebagai item terpisah
                ...(
                    $transaction->platform_fee > 0
                    ? [[
                        'id'       => 'PLATFORM_FEE',
                        'price'    => (int) $transaction->platform_fee,
                        'quantity' => 1,
                        'name'     => 'Biaya Platform (3%)',
                    ]]
                    : []
                ),
            ],
            'customer_details' => [
                'first_name' => $buyer->name,
                'email'      => $buyer->email,
                'phone'      => $buyer->phone,
            ],
            'callbacks' => [
                'finish' => config('midtrans.finish_url'),
            ],
            // Expired 24 jam
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit'       => 'hours',
                'duration'   => 24,
            ],
            // Metode pembayaran yang diizinkan
            'enabled_payments' => [
                'credit_card', 'gopay', 'shopeepay', 'qris',
                'bank_transfer', 'echannel', 'bca_va', 'bni_va',
                'bri_va', 'permata_va', 'cstore', 'dana',
            ],
        ];

        try {
            // Minta Snap Token dari Midtrans
            $snapToken   = \Midtrans\Snap::getSnapToken($params);
            $redirectUrl = \Midtrans\Snap::getSnapUrl($params);
            $expiredAt   = now()->addHours(24);

            DB::beginTransaction();

            TransactionPayment::create([
                'transaction_id'      => $transaction->id,
                'midtrans_order_id'   => $transaction->transaction_code,
                'midtrans_token'      => $snapToken,
                'midtrans_redirect_url' => $redirectUrl,
                'amount'              => $transaction->final_amount,
                'status'              => 'pending',
                'expired_at'          => $expiredAt,
            ]);

            TransactionTimeline::create([
                'transaction_id' => $transaction->id,
                'status'         => 'pending',
                'description'    => 'Link pembayaran Midtrans berhasil dibuat.',
                'created_by'     => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Link pembayaran berhasil dibuat.',
                'data'    => [
                    'snap_token'    => $snapToken,
                    'redirect_url'  => $redirectUrl,
                    'order_id'      => $transaction->transaction_code,
                    'amount'        => $transaction->final_amount,
                    'expired_at'    => $expiredAt,
                    // Client key untuk integrasi mobile SDK
                    'client_key'    => config('midtrans.client_key'),
                    'is_production' => config('midtrans.is_production'),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Midtrans create payment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat link pembayaran. Coba beberapa saat lagi.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/payment/notification (PUBLIC — dipanggil Midtrans)
     * Webhook — Midtrans mengirim notifikasi ke sini setelah pembayaran
     * URL ini harus bisa diakses publik oleh server Midtrans
     */
    public function notification(Request $request): JsonResponse
    {
        $this->initMidtrans();

        try {
            // Verifikasi notifikasi dari Midtrans
            $notification = new \Midtrans\Notification();

            $orderId           = $notification->order_id;
            $statusCode        = $notification->status_code;
            $grossAmount       = $notification->gross_amount;
            $signatureKey      = $notification->signature_key;
            $transactionStatus = $notification->transaction_status;
            $fraudStatus       = $notification->fraud_status;
            $paymentType       = $notification->payment_type;
            $transactionId     = $notification->transaction_id;
            $vaNumbers         = $notification->va_numbers ?? null;

            // Verifikasi signature key untuk keamanan
            $expectedSignature = hash('sha512',
                $orderId . $statusCode . $grossAmount . config('midtrans.server_key')
            );

            if ($signatureKey !== $expectedSignature) {
                Log::warning('Midtrans invalid signature for order: ' . $orderId);
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            // Cari payment berdasarkan order_id
            $payment = TransactionPayment::where('midtrans_order_id', $orderId)->first();

            if (!$payment) {
                Log::warning('Midtrans payment not found for order: ' . $orderId);
                return response()->json(['message' => 'Payment not found'], 404);
            }

            $transaction = $payment->transaction;

            // Tentukan status berdasarkan response Midtrans
            $newPaymentStatus     = 'pending';
            $newTransactionStatus = null;

            if ($transactionStatus === 'capture') {
                if ($fraudStatus === 'challenge') {
                    $newPaymentStatus = 'pending'; // Menunggu review fraud
                } elseif ($fraudStatus === 'accept') {
                    $newPaymentStatus     = 'success';
                    $newTransactionStatus = 'payment_confirmed';
                }
            } elseif ($transactionStatus === 'settlement') {
                $newPaymentStatus     = 'success';
                $newTransactionStatus = 'payment_confirmed';
            } elseif ($transactionStatus === 'pending') {
                $newPaymentStatus = 'pending';
                // VA number baru tersedia saat pending
                if ($vaNumbers && isset($vaNumbers[0])) {
                    $payment->update(['midtrans_va_number' => $vaNumbers[0]->va_number]);
                }
            } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
                $newPaymentStatus     = match($transactionStatus) {
                    'deny'   => 'failure',
                    'expire' => 'expire',
                    'cancel' => 'cancel',
                };
                $newTransactionStatus = 'cancelled';
            }

            DB::beginTransaction();

            // Update payment record
            $paymentData = [
                'midtrans_transaction_id' => $transactionId,
                'midtrans_payment_type'   => $paymentType,
                'status'                  => $newPaymentStatus,
                'midtrans_response'       => $request->all(),
            ];

            if ($newPaymentStatus === 'success') {
                $paymentData['paid_at'] = now();
            }

            $payment->update($paymentData);

            // Update status transaksi
            if ($newTransactionStatus && $transaction->status !== $newTransactionStatus) {
                $transaction->update(['status' => $newTransactionStatus]);

                $description = match($newPaymentStatus) {
                    'success' => "Pembayaran via {$paymentType} berhasil dikonfirmasi oleh Midtrans.",
                    'failure' => 'Pembayaran ditolak oleh Midtrans.',
                    'expire'  => 'Batas waktu pembayaran habis.',
                    'cancel'  => 'Pembayaran dibatalkan.',
                    default   => "Status pembayaran: {$newPaymentStatus}",
                };

                TransactionTimeline::create([
                    'transaction_id' => $transaction->id,
                    'status'         => $newTransactionStatus,
                    'description'    => $description,
                ]);
            }

            DB::commit();

            Log::info("Midtrans notification processed: {$orderId} → {$newPaymentStatus}");
            return response()->json(['message' => 'OK']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Midtrans notification error: ' . $e->getMessage());
            return response()->json(['message' => 'Error processing notification'], 500);
        }
    }

    /**
     * GET /api/v1/payment/status/{transaction}
     * Cek status pembayaran transaksi
     */
    public function status(Request $request, Transaction $transaction): JsonResponse
    {
        if (!$transaction->involves($request->user())) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $payment = TransactionPayment::where('transaction_id', $transaction->id)
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada data pembayaran untuk transaksi ini.',
            ], 404);
        }

        // Cek status terbaru ke Midtrans (opsional, untuk sinkronisasi)
        if ($payment->isPending()) {
            try {
                $this->initMidtrans();
                $statusMidtrans = \Midtrans\Transaction::status($payment->midtrans_order_id);

                // Sinkronisasi jika status sudah berubah di Midtrans
                if (is_object($statusMidtrans) && isset($statusMidtrans->transaction_status)) {
                    $mtStatus = $statusMidtrans->transaction_status;
                    if (in_array($mtStatus, ['settlement', 'capture'])) {
                        $payment->update(['status' => 'success', 'paid_at' => now()]);
                        $transaction->update(['status' => 'payment_confirmed']);
                    }
                }
            } catch (\Exception $e) {
                // Abaikan error sinkronisasi, gunakan data lokal
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_status'   => $payment->fresh()->status,
                'payment_type'     => $payment->midtrans_payment_type,
                'payment_type_label' => $payment->paymentTypeLabel,
                'amount'           => $payment->amount,
                'va_number'        => $payment->midtrans_va_number,
                'snap_token'       => $payment->midtrans_token,
                'redirect_url'     => $payment->midtrans_redirect_url,
                'paid_at'          => $payment->paid_at,
                'expired_at'       => $payment->expired_at,
                'transaction_status' => $transaction->fresh()->status,
            ],
        ]);
    }

    /**
     * POST /api/v1/payment/cancel/{transaction}
     * Batalkan pembayaran yang masih pending
     */
    public function cancel(Request $request, Transaction $transaction): JsonResponse
    {
        if (!$transaction->isBuyer($request->user())) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $payment = TransactionPayment::where('transaction_id', $transaction->id)
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada pembayaran pending yang bisa dibatalkan.',
            ], 422);
        }

        try {
            $this->initMidtrans();
            \Midtrans\Transaction::cancel($payment->midtrans_order_id);

            DB::beginTransaction();

            $payment->update(['status' => 'cancel']);
            $transaction->update(['status' => 'cancelled']);

            TransactionTimeline::create([
                'transaction_id' => $transaction->id,
                'status'         => 'cancelled',
                'description'    => 'Pembayaran dibatalkan oleh pembeli.',
                'created_by'     => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil dibatalkan.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan pembayaran.',
            ], 500);
        }
    }

    /**
     * GET /api/v1/payment/methods
     * Daftar metode pembayaran yang tersedia (untuk tampil di UI)
     */
    public function methods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                [
                    'group' => 'E-Wallet',
                    'methods' => [
                        ['id' => 'gopay',     'name' => 'GoPay',     'logo' => 'gopay.png'],
                        ['id' => 'shopeepay', 'name' => 'ShopeePay', 'logo' => 'shopeepay.png'],
                        ['id' => 'dana',      'name' => 'DANA',      'logo' => 'dana.png'],
                        ['id' => 'qris',      'name' => 'QRIS',      'logo' => 'qris.png'],
                    ],
                ],
                [
                    'group' => 'Transfer Bank (Virtual Account)',
                    'methods' => [
                        ['id' => 'bca_va',      'name' => 'BCA Virtual Account'],
                        ['id' => 'bni_va',      'name' => 'BNI Virtual Account'],
                        ['id' => 'bri_va',      'name' => 'BRI Virtual Account'],
                        ['id' => 'permata_va',  'name' => 'Permata Virtual Account'],
                        ['id' => 'echannel',    'name' => 'Mandiri Bill'],
                    ],
                ],
                [
                    'group' => 'Gerai Retail',
                    'methods' => [
                        ['id' => 'cstore', 'name' => 'Indomaret / Alfamart'],
                    ],
                ],
                [
                    'group' => 'Kartu Kredit',
                    'methods' => [
                        ['id' => 'credit_card', 'name' => 'Visa / Mastercard / JCB'],
                    ],
                ],
            ],
        ]);
    }
}
