<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\Story;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SocialActivityNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $queue = 'notifications';

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public $type,
        public $source,
        public $actor
    ) {}

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

        $messageText = $messages[$this->type] ?? 'Новое уведомление';

        $data = [
            'type' => $this->type,
            'message' => $this->actor->name . ' ' . $messageText,
            'icon' => $this->getIcon(),
            'link' => $this->getLink(),
            'actor' => $this->actor->name,
            'avatar' => $this->actor->avatar_url,
            'actor_id' => $this->actor->id,
        ];

        if ($this->source instanceof Post) {
            $data['post_id'] = $this->source->id;
        }

        if ($this->source instanceof Comment) {
            $data['comment_id'] = $this->source->id;
            $data['post_id'] = $this->source->post_id;
        }

        if ($this->source instanceof Story) {
            $data['story_id'] = $this->source->id;
        }

        return $data;
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
            return '/post/' . $this->source->id;
        }

        if ($this->source instanceof Comment) {
            return '/post/' . $this->source->post_id . '?openComments=true&commentId=' . $this->source->id;
        }

        if ($this->source instanceof Story) {
            return '/profile/' . $this->actor->id;
        }

        if ($this->source instanceof User) {
            return '/profile/' . $this->source->id;
        }

        return '/profile/' . $this->actor->id;
    }
}
