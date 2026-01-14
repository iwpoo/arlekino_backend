<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $currentUser, User $targetUser): bool
    {
        return $currentUser->id === $targetUser->id;
    }

    public function delete(User $currentUser, User $targetUser): bool
    {
        return $currentUser->id === $targetUser->id;
    }
}
