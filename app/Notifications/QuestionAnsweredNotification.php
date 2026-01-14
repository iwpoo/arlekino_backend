<?php

namespace App\Notifications;

use App\Models\ProductQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuestionAnsweredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $queue = 'high';

    public function __construct(public ProductQuestion $question) {}

    public function via($notifiable): array { return ['database']; }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'question_answered',
            'message' => "Получен ответ на ваш вопрос по товару: " . $this->question->product->name,
            'question_id' => $this->question->id,
            'product_id' => $this->question->product_id,
        ];
    }
}
