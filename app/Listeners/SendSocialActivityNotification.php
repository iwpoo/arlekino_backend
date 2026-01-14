<?php

namespace App\Listeners;

use App\Notifications\SocialActivityNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSocialActivityNotification implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public string $queue = 'notifications';

    public int $tries = 2;

    public function handle(object $event): void
    {
        if ($event->targetUser->id === $event->actor->id) {
            return;
        }

        $event->targetUser->notify(
            new SocialActivityNotification(
                $event->type,
                $event->source,
                $event->actor
            )
        );
    }
}
