<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPackage extends Model
{
    protected $fillable = [
        'name', 'description', 'price', 'duration_days',
        'type', 'impression_limit', 'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function productAds(): HasMany
    {
        return $this->hasMany(ProductAd::class);
    }
}