<?php

namespace App\Listeners;

use App\Notifications\OrderCreateNotification;

class SendOrderCreateNotification
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
        $order = $event->order->loadMissing('items.product.user');

        $sellers = $order->items
            ->pluck('product.user')
            ->filter()
            ->unique('id');

        foreach ($sellers as $seller) {
            $seller->notify(new OrderCreateNotification($event->user, $order));
        }
    }
}
