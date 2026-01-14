<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankCard extends Model
{
    protected $fillable = [
        'user_id',
        'card_holder',
        'last_four',
        'brand',
        'token',
        'is_default'
    ];

    protected $hidden = [
        'token'
    ];

    protected static function booted(): void
    {
        static::creating(function ($card) {
            $card->token = encrypt($card->token);
        });

        static::retrieved(function ($card) {
            try {
                $card->token = decrypt($card->token);
            } catch (Exception) {
                $card->token = null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMaskedNumberAttribute(): string
    {
        return "•••• •••• •••• $this->last_four";
    }
}
