<?php

namespace App\Listeners;

use App\Notifications\SellerOrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSellerOrderStatusNotification implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public string $queue = 'high';

    public function handle(object $event): void
    {
        if ($event->order->seller) {
            $event->order->seller->notify(
                new SellerOrderStatusNotification($event->user, $event->order, $event->newStatus)
            );
        }
    }
}
