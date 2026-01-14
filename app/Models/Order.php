<?php

namespace App\Models;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_address_id',
        'courier_id',
        'status',
        'total_amount',
        'shipping_address',
        'payment_method',
        'paid_at',
        'qr_token',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($order) {
            $order->uuid = Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user_address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
    }

    public function isPaid(): bool
    {
        return !is_null($this->paid_at);
    }

    public function generateNewQrToken(): void
    {
        if (!$this->qr_token || now()->gt($this->expires_at)) {
            $this->update([
                'qr_token' => Str::random(32),
                'expires_at' => now()->addHours(24),
            ]);
        }
    }

    public function getQrCodeBase64(): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'scale' => 10,
        ]);
        return (new QRCode($options))->render(route('order.status.update', ['order' => $this->id]));
    }
}
