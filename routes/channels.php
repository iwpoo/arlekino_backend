<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('chat.room.{chatId}', static function (User $user, int $chatId): bool {
    return DB::table('chat_user')
        ->where('chat_id', $chatId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('user.updates.{id}', static function (User $user, int $id): bool {
    return (int) $user->id === $id;
});
