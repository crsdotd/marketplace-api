<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarterRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'buyer_id', 'seller_id',
        'offer_item_name', 'offer_category_id', 'offer_description',
        'offer_images', 'offer_additional_price',
        'status', 'seller_note', 'buyer_note', 'seller_responded_at',
        'midtrans_snap_token', 'midtrans_order_id', 'midtrans_redirect_url',
    ];

    protected $casts = [
        'offer_images'           => 'array',
        'offer_additional_price' => 'decimal:2',
        'seller_responded_at'    => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function offerCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'offer_category_id');
    }

    // ── Helpers ───────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'           => 'Menunggu Respon Seller',
            'seller_reviewing'  => 'Seller Sedang Meninjau',
            'accepted'          => 'Diterima Seller',
            'rejected'          => 'Ditolak Seller',
            'payment_pending'   => 'Menunggu Pembayaran Selisih',
            'payment_confirmed' => 'Pembayaran Dikonfirmasi',
            'completed'         => 'Barter Selesai',
            'cancelled'         => 'Dibatalkan',
            default             => $this->status,
        };
    }

    public function needsAdditionalPayment(): bool
    {
        return $this->offer_additional_price > 0;
    }
}
