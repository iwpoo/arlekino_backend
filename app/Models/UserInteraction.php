<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserInteraction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'target_type', // 'product', 'post', 'user'
        'target_id',
        'interaction_type', // 'view', 'like', 'share', 'comment', 'purchase', 'follow'
        'weight', // Weight of the interaction (e.g., view=1, like=3, share=5, purchase=10)
    ];

    /**
     * Get the user that owns the interaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the target model of the interaction.
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}