<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SellerOrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $queue = 'high';

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public $user,
        public $order,
        public $status
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $messages = [
            'pending' => 'Заказ ожидает обработки',
            'assembling' => 'Заказ собирается',
            'shipped' => 'Заказ передан курьеру',
            'completed' => 'Заказ доставлен',
            'cancelled' => 'Заказ отменен'
        ];

        return [
            'type' => 'order',
            'message' => $messages[$this->status] ?? "Статус заказа изменен: $this->status",
            'link' => '/order/' . $this->order->id,
            'icon' => 'mdi-package-variant-closed',
            'order_id' => $this->order->id,
            'status' => $this->status,
            'actor' => $this->user->name,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ]
        ];
    }
}
