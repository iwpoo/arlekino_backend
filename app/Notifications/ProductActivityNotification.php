<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProductActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $queue = 'notifications';

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public $type,
        public $product,
        public $actor = null
    ) {}

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
            'link' => '/product/' . $this->product->id,
            'actor' => $this->actor?->name,
            'product_id' => $this->product->id,
            'product_image' => $this->product->main_image
        ];
    }
}
