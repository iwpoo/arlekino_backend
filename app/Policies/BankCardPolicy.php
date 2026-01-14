<?php

namespace App\Policies;

use App\Models\BankCard;
use App\Models\User;

class BankCardPolicy
{
    public function update(User $user, BankCard $bankCard): bool
    {
        return $user->id === $bankCard->user_id;
    }

    public function delete(User $user, BankCard $bankCard): bool
    {
        return $user->id === $bankCard->user_id;
    }
}
