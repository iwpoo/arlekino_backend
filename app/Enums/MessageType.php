<?php

namespace App\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum MessageType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case FILE = 'file';

    public static function rule(): In
    {
        return Rule::in(array_column(self::cases(), 'value'));
    }
}