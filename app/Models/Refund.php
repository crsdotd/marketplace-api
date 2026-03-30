<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'transaction_id', 'buyer_id', 'reason', 'description',
        'evidence_images', 'status', 'refund_amount', 'refund_bank',
        'refund_account', 'refund_holder', 'admin_note', 'processed_at',
    ];

    protected $casts = [
        'evidence_images' => 'array',
        'refund_amount'   => 'decimal:2',
        'processed_at'    => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}