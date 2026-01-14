<?php

namespace App\Listeners;

use App\Notifications\NewQuestionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendProductQuestionCreatedNotification implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public string $queue = 'high';

    public function handle(object $event): void
    {
        $question = $event->question->loadMissing('product.user');
        $seller = $question->product->user;

        if ($seller) {
            $seller->notify(new NewQuestionNotification($question));
        }
    }
}
