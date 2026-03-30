<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingPoint extends Model
{
    protected $fillable = [
        'transaction_id', 'name', 'address', 'latitude', 'longitude',
        'status', 'proposed_by', 'scheduled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'latitude'     => 'decimal:8',
        'longitude'    => 'decimal:8',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }
}