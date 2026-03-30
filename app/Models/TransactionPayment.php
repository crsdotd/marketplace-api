<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPayment extends Model
{
    protected $fillable = [
        'transaction_id', 'method', 'bank_name', 'bank_account', 'bank_holder',
        'amount', 'proof_image', 'status', 'paid_at', 'verified_at',
        'verified_by', 'reject_reason',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getProofImageUrlAttribute(): ?string
    {
        return $this->proof_image ? asset('storage/' . $this->proof_image) : null;
    }
}