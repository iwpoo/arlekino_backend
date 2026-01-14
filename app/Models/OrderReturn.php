<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'seller_id',
        'status',
        'refund_amount',
        'logistics_cost',
        'return_method',
        'qr_code',
        'second_qr_code',
        'expires_at',
        'second_expires_at',
        'rejection_reason',
        'completed_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'second_expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the order that this return belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user (customer) who initiated this return.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the seller associated with this return.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get the items in this return.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
