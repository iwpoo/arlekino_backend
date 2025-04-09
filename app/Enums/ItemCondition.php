<?php

namespace App\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum ItemCondition: string
{
    case NEW = 'new';
    case USED = 'used';
    case REFURBISHED = 'refurbished';

    public static function rule(): In
    {
        return Rule::in(array_column(self::cases(), 'value'));
    }
}
