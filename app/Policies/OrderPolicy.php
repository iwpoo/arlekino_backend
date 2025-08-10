<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
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

        // Клиент (владелец заказа)
        if ($order->user_id === $user->id) {
            return in_array(request()->status, ['canceled']);
        }

        return false;
    }
}
