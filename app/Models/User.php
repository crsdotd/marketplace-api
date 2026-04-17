<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_buyer',
        'is_seller',
        'is_admin',
        'avatar',
        'wa_number',
        'is_verified',
        'is_active',
        'balance',
        'fcm_token',
        'provider',
        'provider_id',
        'provider_token',
        'provider_refresh_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
        'provider_token',
        'provider_refresh_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_buyer'          => 'boolean',
        'is_seller'         => 'boolean',
        'is_admin'          => 'boolean',
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

    // ── Role Helpers ──────────────────────────────────────────

    public function isSeller(): bool
    {
        return $this->is_seller === true;
    }

    public function isBuyer(): bool
    {
        return $this->is_buyer === true;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    // Cek apakah user sudah aktifkan mode seller
    public function hasSellerProfile(): bool
    {
        return $this->isSeller() && $this->sellerProfile !== null;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) return null;

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        return asset('storage/' . $this->avatar);
    }

    // Untuk response API — tampilkan role aktif user
    public function getRolesAttribute(): array
    {
        $roles = [];
        if ($this->is_buyer)  $roles[] = 'buyer';
        if ($this->is_seller) $roles[] = 'seller';
        if ($this->is_admin)  $roles[] = 'admin';
        return $roles;
    }
}
