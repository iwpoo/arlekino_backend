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

        $img = Image::make(Storage::disk('public')->path($this->tempPath));

        if ($this->type === 'avatar') {
            $img->fit(300, 300);
        } else {
            $img->resize(1200, null, fn($c) => $c->aspectRatio());
        }

        $img->encode('jpg', 85);
        Storage::disk('public')->put($finalPath, $img);

        if ($user->$field) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->$field));
        }

        $user->update([$field => '/storage/' . $finalPath]);
        Storage::disk('public')->delete($this->tempPath);
    }
}
