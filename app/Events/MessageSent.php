<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public $message
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array|Channel[]|PresenceChannel
     */
    public function broadcastOn(): array|PresenceChannel
    {
        return new PresenceChannel('chat.' . $this->message->chat_id);
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'body' => $this->message->body,
                'chat_id' => $this->message->chat_id,
                'user_id' => $this->message->user_id,
                'created_at' => $this->message->created_at,
                'user' => [
                    'id' => $this->message->user->id,
                    'name' => $this->message->user->name,
                    'email' => $this->message->user->email,
                ]
            ]
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
