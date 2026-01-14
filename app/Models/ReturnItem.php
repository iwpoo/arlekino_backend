<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_id',
        'order_item_id',
        'product_id',
        'quantity',
        'price',
        'reason',
        'comment',
        'photos'
    ];

    protected $casts = [
        'photos' => 'array',
    ];

    /**
     * Get the return that this item belongs to.
     */
    public function return(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    /**
     * Get the order item that this return item is based on.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Get the product being returned.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
