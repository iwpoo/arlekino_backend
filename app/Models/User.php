<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'role',
        'username',
        'phone',
        'avatar_path',
        'email',
        'email_verification_code',
        'email_verified_at',
        'description',
        'gender',
        'currency',
        'default_card_id',
        'authorized_devices',
        'website',
        'shop_cover_path',
        'stories_count',
        'last_story_created_at',
        'has_unseen_stories',
        'last_story_viewed_at',
        'password',
        'city',
        'region_id',
        'content',
        'card_holder',
        'last_four',
        'brand',
        'is_default',
        'token',
        'read_at',
        'product_id',
        'quantity',
    ];

    protected $appends = ['avatar_url', 'shop_cover_url'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'payment_methods' => 'array',
            'authorized_devices' => 'array',
        ];
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::CLIENT->value;
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::SELLER->value;
    }

    public function isCourier(): bool
    {
        return $this->role === UserRole::COURIER->value;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function favoriteProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'favorite_products')
            ->withTimestamps();
    }

    public function favoritePosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'favorite_posts')
            ->withTimestamps();
    }

    public function warehouseAddresses(): HasMany
    {
        return $this->hasMany(WarehouseAddress::class);
    }

    public function bankCards(): User|HasMany
    {
        return $this->hasMany(BankCard::class);
    }

    public function defaultCard(): BelongsTo
    {
        return $this->belongsTo(BankCard::class, 'default_card_id');
    }

    public function addresses(): User|HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function reportsMade(): HasMany
    {
        return $this->hasMany(UserReport::class, 'reporter_id');
    }

    public function reportsReceived(): HasMany
    {
        return $this->hasMany(UserReport::class, 'reported_id');
    }

    public function blocksMade(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    public function blocksReceived(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_id');
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id');
    }

    public function blockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocked_id', 'blocker_id');
    }

    public function getAvatarUrlAttribute(): string
    {
        return Storage::url($this->avatar_path);
    }

    public function getShopCoverUrlAttribute(): ?string
    {
        return $this->shop_cover_path ? Storage::url($this->shop_cover_path) : null;
    }

    public function getWarehouseAddressesAttribute(): array
    {
        return cache()->remember("user.$this->id.warehouses", 3600, function () {
            return $this->warehouseAddresses()->get();
        });
    }

    public function getSellerRatingAttribute(): ?float
    {
        if (!$this->isSeller()) {
            return null;
        }

        $cacheKey = "seller_rating_$this->id";
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $ratedProducts = $this->products()
            ->whereNotNull('rating')
            ->where('rating', '>', 0)
            ->get();

        if ($ratedProducts->isEmpty()) {
            cache()->put($cacheKey, null, 3600);
            return null;
        }

        $totalRating = $ratedProducts->sum('rating');
        $count = $ratedProducts->count();

        if ($count > 0) {
            $averageRating = round($totalRating / $count, 1);
            cache()->put($cacheKey, $averageRating, 3600);
            return $averageRating;
        }

        cache()->put($cacheKey, null, 3600);
        return null;
    }
}
