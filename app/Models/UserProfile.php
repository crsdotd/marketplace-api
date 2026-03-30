<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 'bio', 'address', 'city', 'province',
        'postal_code', 'latitude', 'longitude',
        'ktp_number', 'ktp_photo', 'ktp_verified',
    ];

    protected $casts = [
        'ktp_verified' => 'boolean',
        'latitude'     => 'decimal:8',
        'longitude'    => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
