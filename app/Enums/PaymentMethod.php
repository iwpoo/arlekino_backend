<?php

namespace App\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum PaymentMethod: string
{
    case CARD = 'card';
    case CASH = 'cash';
    case PAYPAL = 'paypal';
    case APPLE_PAY = 'apple_pay';
    case GPAY = 'gpay';

    public static function rule(): In
    {
        return Rule::in(array_column(self::cases(), 'value'));
    }
}