<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{HasOne, HasMany};

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role',
        'avatar', 'wa_number', 'is_verified', 'is_active',
        'balance', 'fcm_token',
    ];

    protected $hidden = ['password', 'remember_token', 'fcm_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_verified'       => 'boolean',
        'is_active'         => 'boolean',
        'balance'           => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function buyerTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'buyer_id');
    }

    public function sellerTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'seller_id');
    }

    public function ratingsGiven(): HasMany
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    // ── Helpers ───────────────────────────────────────────────
    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }
}
