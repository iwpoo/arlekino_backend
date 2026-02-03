<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    public function updated(User $user): void
    {
        Cache::forget("user_auth:$user->id");
    }

    public function deleted(User $user): void
    {
        Cache::forget("user_auth:$user->id");
    }
}
