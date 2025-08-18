<?php

namespace App\Listeners;

use App\Notifications\SellerOrderStatusNotification;

class SendSellerOrderStatusNotification
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
        $event->order->user->notify(
            new SellerOrderStatusNotification($event->order, $event->newStatus)
        );
    }
}
