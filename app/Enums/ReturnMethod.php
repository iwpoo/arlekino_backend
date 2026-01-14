<?php

namespace App\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum ReturnMethod: string
{
    case SELF_RETURN = 'SELF_RETURN';
    case COURIER_RETURN = 'COURIER_RETURN';

    public static function rule(): In
    {
        return Rule::in(array_column(self::cases(), 'value'));
    }
}