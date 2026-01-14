<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'high';

    public function __construct(
        public Message $message
    ) {
        $this->message->loadMissing('user');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.room.{$this->message->chat_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        if (!$this->message->relationLoaded('user')) {
            $this->message->load('user');
        }

        return [
            'message' => [
                'id' => $this->message->id,
                'chat_id' => $this->message->chat_id,
                'user_id' => $this->message->user_id,
                'body' => $this->message->body,
                'type' => $this->message->type ?? 'text',
                'attachment_url' => $this->message->attachment_url,
                'attachment_name' => $this->message->attachment_name,
                'file_size' => $this->message->file_size,
                'created_at' => $this->message->created_at,
                'updated_at' => $this->message->updated_at,
                'user' => $this->message->user ? [
                    'id' => $this->message->user->id,
                    'name' => $this->message->user->name,
                    'username' => $this->message->user->username,
                    'avatar_url' => $this->message->user->avatar_url,
                ] : null,
            ],
        ];
    }
}
