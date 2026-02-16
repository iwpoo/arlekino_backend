<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProcessProfileImageJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 2;

    public function __construct(
        protected int $userId,
        protected string $tempPath,
        protected string $type
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) return;

        $directory = $this->type === 'avatar' ? 'avatars' : 'shop_covers';
        $field = $this->type === 'avatar' ? 'avatar_path' : 'shop_cover_path';

        $filename = basename($this->tempPath);
        $finalPath = "$directory/$filename";

        $img = Image::read(Storage::disk('public')->path($this->tempPath));

        if ($this->type === 'avatar') {
            $img->cover(300, 300);
        } else {
            $img->scale(width: 1200);
        }

        $encoded = $img->toJpeg(85);
        Storage::disk('public')->put($finalPath, $encoded);

        if ($user->$field) {
            Storage::disk('public')->delete($user->$field);
        }

        $user->update([$field => $finalPath]);
        Storage::disk('public')->delete($this->tempPath);
    }
}
