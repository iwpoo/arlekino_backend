<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class ProcessPostFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        protected int $postId,
        protected array $files
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $post = Post::find($this->postId);
        if (!$post) return;

        $attachments = [];

        foreach ($this->files as $file) {
            try {
                $oldPath = $file['path'];
                $newPath = str_replace('temp', 'active', $oldPath);

                $fullOldPath = Storage::disk('public')->path($oldPath);
                $fullNewPath = Storage::disk('public')->path($newPath);

                if (!Storage::disk('public')->exists($oldPath)) continue;

                if (str_contains($file['mime'], 'image')) {
                    $this->optimizeImage($fullOldPath, $fullNewPath);
                    Storage::disk('public')->delete($oldPath);
                } else {
                    Storage::disk('public')->move($oldPath, $newPath);
                }

                $attachments[] = [
                    'post_id' => $post->id,
                    'file_path' => $newPath,
                    'file_type' => strtok($file['mime'], '/'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } catch (Throwable $e) {
                Log::error("Error processing file for post $this->postId: " . $e->getMessage());
            }
        }

        if (!empty($attachments)) {
            $post->files()->insert($attachments);
        }
    }

    protected function optimizeImage(string $sourcePath, string $targetPath): void
    {
        $directory = dirname($targetPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $image = Image::read($sourcePath);

        if ($image->width() > 2000) {
            $image->scale(width: 2000);
        }

        $image->toJpeg(quality: 85)->save($targetPath);
    }
}
