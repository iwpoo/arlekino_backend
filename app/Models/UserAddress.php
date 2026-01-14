<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id',
        'country',
        'region',
        'city',
        'street',
        'house',
        'apartment',
        'postal_code',
        'is_default',
        'full_address'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::saving(function ($address) {
            $parts = [
                $address->country,
                $address->region,
                $address->city,
                $address->street,
                $address->house,
                $address->apartment ? 'кв. ' . $address->apartment : null,
                $address->postal_code
            ];

            $address->full_address = implode(', ', array_filter($parts));
        });
    }
}
