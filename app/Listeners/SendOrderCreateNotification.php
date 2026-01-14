<?php

namespace App\Listeners;

use App\Notifications\OrderCreateNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderCreateNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'high';

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
