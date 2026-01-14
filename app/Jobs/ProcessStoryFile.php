<?php

namespace App\Jobs;

use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;
use Throwable;

class ProcessStoryFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(protected Story $story) {
        $this->onQueue('low');
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        if (!$this->story->exists) return;

        $inputPath = $this->story->file_path;
        $disk = Storage::disk('public');

        if (!$disk->exists($inputPath)) {
            Log::error("Story file missing: $inputPath");
            return;
        }

        try {
            if ($this->story->file_type === 'video') {
                $this->processVideo($inputPath);
            } else {
                $this->processImage($inputPath);
            }

            $this->story->update(['is_ready' => true]);

            NotifyFollowersOfNewStory::dispatch($this->story);
        } catch (Throwable $e) {
            Log::error("Story processing failed: " . $e->getMessage());

            $this->story->update(['is_ready' => false]);
            throw $e;
        }
    }

    protected function processVideo(string $inputPath): void
    {
        $outputPath = 'stories/' . date('Y/m') . '/' . uniqid() . '.mp4';

        $lowBitrateFormat = (new X264('libmp3lame', 'libx264'))
            ->setKiloBitrate(1500);

        FFMpeg::fromDisk('public')
            ->open($inputPath)
            ->export()
            ->toDisk('public')
            ->inFormat($lowBitrateFormat)
            ->save($outputPath);

        Storage::disk('public')->delete($inputPath);
        $this->story->update(['file_path' => $outputPath]);
    }

    protected function processImage(string $inputPath): void
    {
        $img = Image::make(Storage::disk('public')->path($inputPath));

        $img->resize(1080, 1920, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->encode('jpg', 85);

        $outputPath = 'stories/' . date('Y/m') . '/' . uniqid() . '.jpg';
        Storage::disk('public')->put($outputPath, (string) $img);

        Storage::disk('public')->delete($inputPath);
        $this->story->update(['file_path' => $outputPath]);
    }
}
