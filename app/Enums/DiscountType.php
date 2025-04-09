<?php

namespace App\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum DiscountType: string
{
    case PERCENT = 'percent';
    case FIXED_SUM = 'fixedSum';

    public static function rule(): In
    {
        return Rule::in(array_column(self::cases(), 'value'));
    }
}
