<?php

namespace App\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum ReturnReason: string
{
    case WRONG_SIZE = 'wrong_size';
    case DISLIKED_COLOR_DESIGN = 'disliked_color_design';
    case DOES_NOT_MATCH_DESCRIPTION = 'does_not_match_description';
    case DEFECTIVE_DAMAGED = 'defective_damaged';
    case CHANGED_MIND = 'changed_mind';

    public static function rule(): In
    {
        return Rule::in(array_column(self::cases(), 'value'));
    }
}