<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdPayment extends Model
{
    protected $fillable = [
        'product_ad_id', 'user_id', 'amount',
        'bank_name', 'bank_account', 'proof_image', 'status', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function productAd(): BelongsTo
    {
        return $this->belongsTo(ProductAd::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}