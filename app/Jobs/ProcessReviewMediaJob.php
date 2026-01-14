<?php

namespace App\Jobs;

use App\Models\Review;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProcessReviewMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        protected int $reviewId,
        protected array $tempPaths
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $review = Review::find($this->reviewId);
        if (!$review) return;

        $finalPhotos = [];

        foreach ($this->tempPaths['photos'] as $tempPath) {
            $filename = basename($tempPath);
            $finalPath = "reviews/photos/$filename";

            $img = Image::make(Storage::disk('public')->path($tempPath))
                ->resize(1200, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('jpg', 85);

            Storage::disk('public')->put($finalPath, $img);
            $finalPhotos[] = '/storage/' . $finalPath;

            Storage::disk('public')->delete($tempPath);
        }

        $finalVideoPath = null;
        if ($this->tempPaths['video']) {
            $tempVideo = $this->tempPaths['video'];
            $videoFilename = basename($tempVideo);
            $finalVideoPath = "reviews/videos/$videoFilename";

            Storage::disk('public')->move($tempVideo, $finalVideoPath);
            $finalVideoPath = '/storage/' . $finalVideoPath;
        }

        $review->update([
            'photos' => $finalPhotos,
            'video_path' => $finalVideoPath
        ]);
    }
}
