<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderCreateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $queue = 'high';

    public function __construct(
        public $user,
        public $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'order',
            'message' => "Поступил новый заказ!",
            'link' => '/order/' . $this->order->id,
            'icon' => 'mdi-cart',
            'order_id' => $this->order->id,
            'actor' => $this->order->user->name,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ]
        ];
    }
}
