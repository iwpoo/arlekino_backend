<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool {
        return $user->id === $order->user_id || $order->sellerOrders()->where('seller_id', $user->id)->exists();
    }

    public function store(User $user): bool
    {
        if ($user->isClient()) {
            return true;
        }

        return false;
    }

    public function updateStatus(User $user, Order $order): bool
    {
        if ($user->isSeller()) {
            return in_array(request()->status, ['assembling', 'canceled']);
        }

        if ($user->isCourier()) {
            if ($order->courier_id !== $user->id) {
                return false;
            }
            return in_array(request()->status, ['shipped', 'completed']);
        }

        if ($user->isClient() && $order->user_id === $user->id) {
            return request()->status == 'canceled';
        }

        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
}
