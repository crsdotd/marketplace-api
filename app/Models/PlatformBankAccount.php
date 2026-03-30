<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformBankAccount extends Model
{
    protected $fillable = [
        'bank_name', 'account_number', 'account_holder', 'logo', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];
}