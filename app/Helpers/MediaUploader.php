<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class MediaUploader
{
    public function upload($file, string $disk, string $directory, ?string $oldPath = null): string
    {
        if ($oldPath && Storage::exists($oldPath)) {
            Storage::delete($oldPath);
        }

        return $file->store($directory, $disk);
    }
}
