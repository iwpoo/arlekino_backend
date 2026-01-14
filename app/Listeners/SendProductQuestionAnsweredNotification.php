<?php

namespace App\Listeners;

use App\Notifications\QuestionAnsweredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendProductQuestionAnsweredNotification implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public string $queue = 'default';

    public function handle(object $event): void
    {
        $question = $event->question->loadMissing('user');
        $customer = $question->user;

        if ($customer) {
            $customer->notify(new QuestionAnsweredNotification($question));
        }
    }
}
