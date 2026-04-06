<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne, HasMany};

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transaction_code', 'buyer_id', 'seller_id', 'product_id',
        'quantity', 'price', 'total_price', 'shipping_cost', 'platform_fee',
        'final_amount', 'type', 'status', 'shipping_address', 'shipping_city',
        'tracking_number', 'notes', 'payment_deadline', 'auto_complete_at',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'total_price'      => 'decimal:2',
        'shipping_cost'    => 'decimal:2',
        'platform_fee'     => 'decimal:2',
        'final_amount'     => 'decimal:2',
        'payment_deadline' => 'datetime',
        'auto_complete_at' => 'datetime',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(TransactionPayment::class);
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(TransactionTimeline::class)->orderBy('created_at');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    public function liveLocations(): HasMany
    {
        return $this->hasMany(LiveLocation::class);
    }

    public function meetingPoints(): HasMany
    {
        return $this->hasMany(MeetingPoint::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function isBuyer(User $user): bool
    {
        return $this->buyer_id === $user->id;
    }

    public function isSeller(User $user): bool
    {
        return $this->seller_id === $user->id;
    }

    public function involves(User $user): bool
    {
        return $this->isBuyer($user) || $this->isSeller($user);
    }
}
