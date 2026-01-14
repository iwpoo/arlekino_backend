<?php

namespace App\Events;

use App\Models\ProductQuestion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductQuestionAnswered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProductQuestion $question) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
