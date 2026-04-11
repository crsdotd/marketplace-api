<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'category_id', 'title', 'slug', 'description',
        'price', 'stock', 'condition', 'status', 'transaction_type',
        'location_city', 'location_province', 'location_place_id', 'location_address',
        'latitude', 'longitude',
        'view_count', 'rating_avg', 'rating_count', 'is_promoted', 'promoted_until',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'is_promoted'    => 'boolean',
        'promoted_until' => 'datetime',
        'latitude'       => 'decimal:8',
        'longitude'      => 'decimal:8',
    ];

    // ── Relationships ──────────────────────────────────────────
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasMany
    {
        return $this->hasMany(ProductImage::class)->where('is_primary', true);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ProductTag::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class)->orderByDesc('created_at');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeNearby(Builder $query, float $lat, float $lng, int $radiusKm = 50): Builder
    {
        return $query->selectRaw("*, (
            6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )
        ) AS distance", [$lat, $lng, $lat])
        ->having('distance', '<=', $radiusKm)
        ->orderBy('distance');
    }

    public function scopePromoted(Builder $query): Builder
    {
        return $query->where('is_promoted', true)
                     ->where('promoted_until', '>', now());
    }
}
