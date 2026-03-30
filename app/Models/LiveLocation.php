<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class LiveLocation extends Model
{
    protected $fillable = [
        'transaction_id', 'user_id', 'latitude', 'longitude',
        'is_sharing', 'share_token', 'expires_at',
    ];

    protected $casts = [
        'is_sharing' => 'boolean',
        'expires_at' => 'datetime',
        'latitude'   => 'decimal:8',
        'longitude'  => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(LocationHistory::class)->orderByDesc('recorded_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }
}