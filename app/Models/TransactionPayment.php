<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPayment extends Model
{
    protected $fillable = [
        'transaction_id',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'midtrans_token',
        'midtrans_redirect_url',
        'midtrans_va_number',
        'midtrans_payment_type',
        'amount',
        'status',
        'midtrans_response',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'amount'             => 'decimal:2',
        'paid_at'            => 'datetime',
        'expired_at'         => 'datetime',
        'midtrans_response'  => 'array',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'success';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // Label metode pembayaran yang lebih mudah dibaca
    public function getPaymentTypeLabelAttribute(): string
    {
        return match($this->midtrans_payment_type) {
            'credit_card'        => 'Kartu Kredit',
            'bank_transfer'      => 'Transfer Bank',
            'echannel'           => 'Mandiri Bill',
            'gopay'              => 'GoPay',
            'shopeepay'          => 'ShopeePay',
            'qris'               => 'QRIS',
            'dana'               => 'DANA',
            'cstore'             => 'Indomaret / Alfamart',
            'akulaku'            => 'Akulaku',
            default              => $this->midtrans_payment_type ?? 'Tidak diketahui',
        };
    }
}
