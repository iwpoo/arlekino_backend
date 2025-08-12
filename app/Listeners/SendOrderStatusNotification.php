<?php

namespace App\Listeners;

use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderStatusNotification
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
        // Уведомление покупателю
        $event->order->user->notify(
            new OrderStatusNotification($event->order, $event->newStatus)
        );

        // Уведомление продавцу (если статус требует)
        if (in_array($event->newStatus, ['pending', 'cancelled'])) {
            $event->order->product->user->notify(
                new OrderStatusNotification($event->order, $event->newStatus, true)
            );
        }
    }
}
