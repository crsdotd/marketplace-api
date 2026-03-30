<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'live_location_id', 'latitude', 'longitude',
        'accuracy', 'speed', 'recorded_at'
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude'    => 'decimal:8',
        'longitude'   => 'decimal:8',
    ];

    public function liveLocation(): BelongsTo
    {
        return $this->belongsTo(LiveLocation::class);
    }
}