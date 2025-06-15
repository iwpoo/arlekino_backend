<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

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
        'website',
        'shop_cover_path',
        'stories_count',
        'last_story_created_at',
        'has_unseen_stories',
        'last_story_viewed_at',
        'password',
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
        return $this->role === 'client';
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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

    public function warehouseAddresses(): HasMany
    {
        return $this->hasMany(WarehouseAddress::class);
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
}
