<?php

namespace App\Listeners;

use App\Notifications\SocialActivityNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSocialActivityNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $event->targetUser->notify(
            new SocialActivityNotification(
                $event->type,
                $event->source,
                $event->actor
            )
        );
    }
}
