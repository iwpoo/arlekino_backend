<?php

namespace App\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum ReturnStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case IN_TRANSIT = 'in_transit';
    case RECEIVED = 'received';
    case CONDITION_OK = 'condition_ok';
    case CONDITION_BAD = 'condition_bad';
    case REFUND_INITIATED = 'refund_initiated';
    case COMPLETED = 'completed';
    case REJECTED_BY_WAREHOUSE = 'rejected_by_warehouse';
    case IN_TRANSIT_BACK_TO_CUSTOMER = 'in_transit_back_to_customer';

    public static function rule(): In
    {
        return Rule::in(array_column(self::cases(), 'value'));
    }
}