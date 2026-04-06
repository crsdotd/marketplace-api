<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Rating extends Model
{
    protected $fillable = [
        'transaction_id', 'rater_id', 'rated_id', 'product_id',
        'type', 'rating', 'review', 'images',
    ];

    protected $casts = ['images' => 'array'];

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function rated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
