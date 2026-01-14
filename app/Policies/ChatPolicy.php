<?php

namespace App\Policies;

use App\Models\Chat;
use App\Models\User;

class ChatPolicy
{
    public function view(User $user, Chat $chat): bool
    {
        return $chat->users()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Chat $chat): bool
    {
        if ($chat->is_private) {
            return $chat->users()->where('users.id', $user->id)->exists();
        }

        return $chat->created_by === $user->id;
    }

    public function delete(User $user, Chat $chat): bool
    {
        return $chat->users()->where('users.id', $user->id)->exists();
    }
}
