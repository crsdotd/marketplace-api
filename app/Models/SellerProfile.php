<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerProfile extends Model
{
    protected $fillable = [
        'user_id', 'shop_name', 'shop_description',
        'shop_banner', 'total_sold', 'rating_avg', 'rating_count',
    ];

    protected $casts = [
        'rating_avg' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getShopBannerUrlAttribute(): ?string
    {
        return $this->shop_banner ? asset('storage/' . $this->shop_banner) : null;
    }
}
