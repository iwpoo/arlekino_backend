<?php

use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Канал для чатов (приватный)
Broadcast::channel('chat.{chatId}', function (User $user, $chatId) {
    $chat = Chat::findOrFail($chatId);
    return $chat->users->contains('id', $user->id);
});
