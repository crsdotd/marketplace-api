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
        'social_media', // array of {name, url}
    ];

    protected $casts = [
        'ktp_verified' => 'boolean',
        'latitude'     => 'decimal:8',
        'longitude'    => 'decimal:8',
        'social_media' => 'array', // otomatis encode/decode JSON
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Validasi & bersihkan data social media sebelum disimpan
     * Hapus entry yang nama atau url-nya kosong
     */
    public function setSocialMediaAttribute(?array $value): void
    {
        if (!$value) {
            $this->attributes['social_media'] = null;
            return;
        }

        $cleaned = collect($value)
            ->filter(fn($item) =>
                !empty(trim($item['name'] ?? '')) &&
                !empty(trim($item['url'] ?? ''))
            )
            ->map(fn($item) => [
                'name' => trim($item['name']),
                'url'  => trim($item['url']),
            ])
            ->values()
            ->toArray();

        $this->attributes['social_media'] = json_encode($cleaned);
    }
}
