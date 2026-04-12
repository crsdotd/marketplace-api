<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'transaction_id', 'buyer_id', 'seller_id',
        'reason', 'description', 'evidence_images',
        'status', 'refund_amount',
        'refund_bank', 'refund_account', 'refund_holder',
        // Seller response
        'seller_note', 'seller_responded_at',
        'is_escalated', 'escalated_at',
        // Admin
        'admin_note', 'processed_at',
    ];

    protected $casts = [
        'evidence_images'      => 'array',
        'refund_amount'        => 'decimal:2',
        'seller_responded_at'  => 'datetime',
        'processed_at'         => 'datetime',
        'escalated_at'         => 'datetime',
        'is_escalated'         => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    // ── Status Helpers ────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSellerReviewing(): bool
    {
        return $this->status === 'seller_reviewing';
    }

    public function isSellerApproved(): bool
    {
        return $this->status === 'seller_approved';
    }

    public function isSellerRejected(): bool
    {
        return $this->status === 'seller_rejected';
    }

    public function isEscalated(): bool
    {
        return $this->is_escalated === true;
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    // Label status yang mudah dibaca
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'           => 'Menunggu Respon Seller',
            'seller_reviewing'  => 'Seller Sedang Mereview',
            'seller_approved'   => 'Disetujui Seller',
            'seller_rejected'   => 'Ditolak Seller',
            'admin_reviewing'   => 'Eskalasi ke Admin',
            'approved'          => 'Disetujui Admin',
            'rejected'          => 'Ditolak',
            'processed'         => 'Dana Sudah Dikembalikan',
            default             => $this->status,
        };
    }
}
