<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductActivityNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public $type,
        public $product,
        public $actor = null
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

    public function toDatabase($notifiable): array
    {
        $messages = [
            'question' => 'задал(а) вопрос о товаре',
            'answer' => 'ответил(а) на ваш вопрос',
            'review' => 'оставил(а) отзыв о товаре',
            'favorite' => 'добавил(а) товар в избранное',
            'price_drop' => 'снизил(а) цену на товар'
        ];

        return [
            'type' => 'product',
            'message' => $messages[$this->type] ?? 'Новое уведомление',
            'icon' => 'mdi-shopping',
            'link' => route('products.show', $this->product->id),
            'actor' => $this->actor?->name,
            'product_id' => $this->product->id,
            'product_image' => $this->product->main_image
        ];
    }
}
