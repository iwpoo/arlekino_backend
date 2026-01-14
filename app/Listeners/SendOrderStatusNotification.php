<?php

namespace App\Listeners;

use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderStatusNotification implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public string $queue = 'high';

    public function handle(object $event): void
    {
        $event->order->user->notify(
            new OrderStatusNotification($event->user, $event->order, $event->newStatus)
        );
    }
}
