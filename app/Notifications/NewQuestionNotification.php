<?php

namespace App\Notifications;

use App\Models\ProductQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewQuestionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $queue = 'high';

    public function __construct(public ProductQuestion $question) {}

    public function via($notifiable): array { return ['database']; }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'new_question',
            'message' => "Новый вопрос к товару: " . $this->question->product->name,
            'question_id' => $this->question->id,
            'product_id' => $this->question->product_id,
            'user_id' => $this->question->user_id
        ];
    }
}
