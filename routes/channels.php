<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web']]);

Broadcast::channel('chat.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
