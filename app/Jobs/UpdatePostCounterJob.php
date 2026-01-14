<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class UpdatePostCounterJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(protected int $postId, protected string $column) {
        $this->onQueue('analytics');
    }

    public function handle(): void
    {
        $allowedColumns = ['views_count', 'likes_count', 'shares_count'];

        if (!in_array($this->column, $allowedColumns)) {
            return;
        }

        Post::where('id', $this->postId)->increment($this->column);
    }
}
