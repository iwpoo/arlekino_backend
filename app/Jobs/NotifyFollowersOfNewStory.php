<?php

namespace App\Jobs;

use App\Models\Story;
use App\Models\User;
use App\Notifications\SocialActivityNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotifyFollowersOfNewStory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        protected Story $story
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $author = $this->story->user;

        $author->followers()
            ->select('users.id')
            ->where('follower_id', '!=', $author->id)
            ->chunkById(1000, function ($followers) use ($author) {
                $notifications = [];
                $now = now();

                foreach ($followers as $follower) {
                    $notifications[] = [
                        'id' => Str::uuid()->toString(),
                        'type' => SocialActivityNotification::class,
                        'notifiable_type' => User::class,
                        'notifiable_id' => $follower->id,
                        'data' => json_encode([
                            'type' => 'new_story',
                            'message' => "$author->name добавил(а) новую историю",
                            'story_id' => $this->story->id,
                            'avatar' => $author->avatar_path
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('notifications')->insert($notifications);
            });
    }
}
