<?php

namespace App\Services;

use App\Helpers\MediaUploader;
use App\Models\User;
use App\Services\Contracts\ProfileServiceInterface;
use DB;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableAlias;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ClientProfileService implements ProfileServiceInterface
{
    protected AuthenticatableAlias|null|User $user;

    public function __construct(protected MediaUploader $uploader)
    {
        $this->user = Auth::user();
    }

    /**
     * @throws Throwable
     */
    public function update(array $data): array
    {
        DB::beginTransaction();

        try {
//            if (isset($data['avatar'])) {
//                $this->user->avatar_path = $this->uploader->upload($data['avatar'], 'public', 'avatars', $this->user->avatar_path);
//                unset($data['avatar']);
//            }

            $this->user->update($data);

            DB::commit();

            return ['user' => $this->user->fresh()];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
