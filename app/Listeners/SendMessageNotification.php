<?php

namespace App\Listeners;

use App\Notifications\MessageNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SendMessageNotification implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public string $queue = 'notifications';

    public function handle(object $event): void
    {
        $message = $event->message;

        $recipients = $message->chat->users()
            ->where('users.id', '!=', $message->user_id)
            ->get(['users.id', 'users.name']);

        if ($recipients->isEmpty()) {
            return;
        }

        $chatId = $message->chat_id;

        $keys = $recipients->map(fn($u) => "active_chat_{$u->id}_$chatId")->toArray();
        $statuses = Cache::many($keys);

        foreach ($recipients as $recipient) {
            $cacheKey = "active_chat_{$recipient->id}_$chatId";

            if (empty($statuses[$cacheKey])) {
                $recipient->notify(new MessageNotification(
                    $message,
                    $message->user,
                    $message->chat
                ));
            }
        }
    }
}

