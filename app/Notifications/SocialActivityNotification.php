<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SocialActivityNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public $type,
        public $source,
        public $actor
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
            'like' => 'лайкнул(а) ваш пост',
            'comment' => 'прокомментировал(а) ваш пост',
            'follow' => 'подписался(ась) на вас',
            'mention' => 'упомянул(а) вас в посте',
            'new_post' => 'опубликовал(а) новый пост',
            'new_story' => 'добавил(а) новую историю'
        ];

        return [
            'type' => $this->type,
            'message' => $messages[$this->type] ?? 'Новое уведомление',
            'icon' => $this->getIcon(),
            'link' => $this->getLink(),
            'actor' => $this->actor->name,
            'avatar' => $this->actor->avatar_url
        ];
    }

    private function getIcon(): string
    {
        $icons = [
            'like' => 'mdi-heart',
            'comment' => 'mdi-comment',
            'follow' => 'mdi-account-plus',
            'mention' => 'mdi-at',
            'new_post' => 'mdi-newspaper',
            'new_story' => 'mdi-play-circle'
        ];

        return $icons[$this->type] ?? 'mdi-bell';
    }

    private function getLink(): string
    {
        if ($this->source instanceof Post) {
            return route('posts.show', $this->source->id);
        }

        if ($this->source instanceof Story) {
            return route('stories.show', $this->source->id);
        }

        return route('user.profile', $this->actor->id);
    }
}
