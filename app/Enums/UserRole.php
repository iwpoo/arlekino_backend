<?php

namespace App\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum UserRole: string
{
    case CLIENT = 'client';
    case SELLER = 'seller';
    case COURIER = 'courier';

    public static function rule(): In
    {
        return Rule::in(array_column(self::cases(), 'value'));
    }
}