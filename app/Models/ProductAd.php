<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};

class ProductAd extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'ad_package_id',
        'status', 'impressions', 'clicks', 'started_at', 'expires_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adPackage(): BelongsTo
    {
        return $this->belongsTo(AdPackage::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(AdPayment::class);
    }
}