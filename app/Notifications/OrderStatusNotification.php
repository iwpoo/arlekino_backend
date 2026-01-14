<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $queue = 'high';

    public function __construct(
        public $user,
        public $order,
        public $status,
        public $forSeller = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'order',
            'message' => $this->getStatusMessage(),
            'icon' => 'mdi-package-variant-closed',
            'link' => $this->forSeller
                ? '/order/' . $this->order->id
                : '/order/' . $this->order->id,
            'order_id' => $this->order->id,
            'status' => $this->status,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ]
        ];
    }

    private function getStatusMessage(): string
    {
        $messages = [
            'pending' => 'Ваш заказ ожидает обработки',
            'assembling' => 'Продавец собирает ваш заказ',
            'shipped' => 'Заказ передан курьеру',
            'completed' => 'Заказ доставлен!',
            'cancelled' => 'Заказ отменен'
        ];

        return $messages[$this->status] ?? "Статус заказа изменен: $this->status";
    }
}
