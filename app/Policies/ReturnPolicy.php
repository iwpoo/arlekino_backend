<?php

namespace App\Policies;

use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReturnPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isClient() || $user->isSeller();
    }

    public function view(User $user, OrderReturn $return): bool
    {
        return ($user->isClient() && $return->user_id === $user->id) ||
               ($user->isSeller() && $return->seller_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isClient();
    }

    public function update(User $user, OrderReturn $return): bool
    {
        return $user->isSeller() && $return->seller_id === $user->id;
    }

    public function approve(User $user, OrderReturn $return): bool
    {
        return $user->isSeller() && $return->seller_id === $user->id && $return->status === 'pending';
    }

    public function reject(User $user, OrderReturn $return): bool
    {
        return $user->isSeller() && $return->seller_id === $user->id && $return->status === 'pending';
    }

    public function updateStatus(User $user, OrderReturn $return, string $status = null): bool
    {
        if ($user->isClient() && $return->user_id === $user->id) {
            return $status == 'in_transit_back_to_customer';
        }

        if ($user->isSeller() && $return->seller_id === $user->id) {
            return true;
        }

        return false;
    }

    public function generateQR(User $user, OrderReturn $return): bool
    {
        return ($user->isClient() && $return->user_id === $user->id) ||
               ($user->isSeller() && $return->seller_id === $user->id);
    }

    public function regenerateQR(User $user, OrderReturn $return): bool
    {
        return $user->isClient() && $return->user_id === $user->id;
    }

    public function scanQR(User $user, OrderReturn $return): bool
    {
        return ($user->isClient() && $return->user_id === $user->id) ||
               ($user->isSeller() && $return->seller_id === $user->id);
    }
}
