<?php

namespace App\Services;

use App\Helpers\MediaUploader;
use App\Jobs\ProcessProfileImageJob;
use App\Services\Contracts\ProfileServiceInterface;
use DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ClientProfileService implements ProfileServiceInterface
{
    public function __construct(protected MediaUploader $uploader) {}

    public function update(array $data): array
    {
        $user = Auth::user();

        if (isset($data['avatar'])) {
            $tempPath = $data['avatar']->store('temp/avatars', 'public');

            ProcessProfileImageJob::dispatch($user->id, $tempPath, 'avatar');

            unset($data['avatar']);
        }

        $user->update($data);

        Cache::forget("user_profile_$user->id");

        return ['user' => $user->fresh()];
    }
}
