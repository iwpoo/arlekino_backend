<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public $order,
        public $status,
        public $forSeller = false
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, message: string, icon: string, link: string, actor: mixed, product_id: mixed}
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'order',
            'message' => $this->getStatusMessage(),
            'icon' => 'mdi-package-variant-closed',
            'link' => $this->forSeller
                ? route('seller.orders.show', $this->order->id)
                : route('orders.show', $this->order->id),
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
