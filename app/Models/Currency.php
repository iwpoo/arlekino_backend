<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'symbol', 'rate_to_base', 'is_active'
    ];

    protected $casts = [
        'rate_to_base' => 'float',
        'is_active' => 'boolean',
    ];
}
