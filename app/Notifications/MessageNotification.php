<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $queue = 'notifications';

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public $message,
        public $sender,
        public $chat = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $messageText = $this->message->text ?? $this->message->body ?? '';

        $preview = mb_strlen($messageText) > 50
            ? mb_substr($messageText, 0, 50) . '...'
            : $messageText;

        $chatId = $this->chat ? $this->chat->id : ($this->message->chat_id ?? null);

        return [
            'type' => 'message',
            'message' => $this->sender->name . ' отправил(а) вам сообщение',
            'icon' => 'mdi-message-text',
            'link' => $chatId ? '/chat/' . $chatId : '/chat',
            'actor' => $this->sender->name,
            'avatar' => $this->sender->avatar_url,
            'sender_id' => $this->sender->id,
            'chat_id' => $chatId,
            'message_preview' => $preview
        ];
    }
}

