<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'discount_type' => DiscountType::class,
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    protected array $dates = [
        'start_date',
        'end_date',
    ];

    /**
     * Get the seller that owns the promotion.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the products for the promotion.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product');
    }

    /**
     * Scope a query to only include active promotions.
     */
    public function scopeActive(Builder $query): void
    {
        $now = now();
        $query->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $now);
            });
    }

    /**
     * Scope a query to only include scheduled promotions.
     */
    public function scopeScheduled(Builder $query): void
    {
        $now = now();
        $query->where('status', 'scheduled')
            ->where('start_date', '>', $now);
    }

    /**
     * Scope a query to only include expired promotions.
     */
    public function scopeExpired(Builder $query): void
    {
        $now = now();
        $query->where('status', 'expired')
            ->orWhere(function ($q) use ($now) {
                $q->whereNotNull('end_date')
                  ->where('end_date', '<', $now);
            });
    }

    /**
     * Check if the promotion is currently active.
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->status === 'active' &&
               ($this->start_date === null || $this->start_date <= $now) &&
               ($this->end_date === null || $this->end_date >= $now);
    }

    /**
     * Check if the promotion is scheduled.
     */
    public function isScheduled(): bool
    {
        $now = now();
        return $this->status === 'scheduled' &&
               $this->start_date !== null &&
               $this->start_date > $now;
    }

    /**
     * Check if the promotion is expired.
     */
    public function isExpired(): bool
    {
        $now = now();
        return $this->status === 'expired' ||
               ($this->end_date !== null && $this->end_date < $now);
    }

    /**
     * Calculate the discounted price for a given original price.
     */
    public function calculateDiscountedPrice(float $originalPrice): float
    {
        if ($this->discount_type === DiscountType::PERCENT) {
            return $originalPrice - ($originalPrice * $this->discount_value / 100);
        } elseif ($this->discount_type === DiscountType::FIXED_SUM) {
            return max(0, $originalPrice - $this->discount_value);
        }

        return $originalPrice;
    }

    public function scopeByStatus($query, $status)
    {
        $now = now();
        return match ($status) {
            'active' => $query->where('start_date', '<=', $now)->where('end_date', '>=', $now),
            'scheduled' => $query->where('start_date', '>', $now),
            'expired' => $query->where('end_date', '<', $now),
            default => $query
        };
    }

    public function getCalculatedStatusAttribute(): string
    {
        $now = now();
        if ($this->start_date > $now) return 'scheduled';
        if ($this->end_date < $now) return 'expired';
        return 'active';
    }
}
