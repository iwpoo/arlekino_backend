<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case ASSEMBLING = 'assembling';
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
