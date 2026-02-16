<?php

namespace App\Services;

use App\Helpers\MediaUploader;
use App\Jobs\ProcessProfileImageJob;
use App\Models\User;
use App\Services\Contracts\ProfileServiceInterface;
use DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SellerProfileService implements ProfileServiceInterface
{
    public function __construct(protected MediaUploader $uploader) {}

    public function update(array $data): array
    {
        $user = Auth::user();

        try {
            return DB::transaction(function () use ($user, $data) {
                if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
                    $tempPath = $data['avatar']->store('temp/avatars', 'public');
                    ProcessProfileImageJob::dispatch($user->id, $tempPath, 'avatar');
                }

                if (isset($data['shop_cover'])) {
                    $tempPath = $data['shop_cover']->store('temp/covers', 'public');
                    ProcessProfileImageJob::dispatch($user->id, $tempPath, 'shop_cover');
                }

                if (isset($data['warehouse_addresses'])) {
                    $this->syncWarehouseAddresses($user, $data['warehouse_addresses']);
                }

                $fillableData = array_diff_key($data, array_flip(['avatar', 'shop_cover', 'warehouse_addresses']));
                $user->update($fillableData);

                Cache::forget("user_profile_$user->id");

                return ['user' => $user->fresh()];
            });
        } catch (Throwable $e) {
            Log::error("Profile update failed for User [$user->id]: " . $e->getMessage(), [
                'data_keys' => array_keys($data)
            ]);

            throw new RuntimeException("Не удалось обновить профиль. Попробуйте позже.");
        }
    }

    protected function syncWarehouseAddresses(User $user, array $addresses): void
    {
        $keepIds = collect($addresses)->pluck('id')->filter()->toArray();
        $user->warehouseAddresses()->whereNotIn('id', $keepIds)->delete();

        if (empty($addresses)) return;

        $upsertData = array_map(fn($addr) => [
            'id' => $addr['id'] ?? null,
            'address' => $addr['address'],
            'is_default' => $addr['is_default'] ?? false,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $addresses);

        $user->warehouseAddresses()->upsert(
            $upsertData,
            ['id'],
            ['address', 'is_default', 'updated_at']
        );
    }
}
