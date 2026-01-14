<?php

namespace App\Policies;

use App\Models\SellerOrder;
use App\Models\User;

class SellerOrderPolicy
{
    public function update(User $user, SellerOrder $sellerOrder): bool
    {
        return (int) $user->id === (int) $sellerOrder->seller_id;
    }
}
