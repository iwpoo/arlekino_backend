<?php

namespace App\Services;

use App\Helpers\MediaUploader;
use App\Models\User;
use App\Services\Contracts\ProfileServiceInterface;
use DB;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableAlias;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

class SellerProfileService implements ProfileServiceInterface
{
    protected AuthenticatableAlias|null|User $user;

    public function __construct(protected MediaUploader $uploader)
    {
        $this->user = Auth::user()->load(['warehouseAddresses']);
    }

    /**
     * @throws Throwable
     */
    public function update(array $data): array
    {
        DB::beginTransaction();

        try {
//            if (isset($data['avatar'])) {
//                $this->user->avatar_path = $this->uploader->upload(
//                    $data['avatar'],
//                    'public',
//                    'avatars',
//                    $this->user->avatar_path
//                );
//                unset($data['avatar']);
//            }
//
//            if (isset($data['shop_cover'])) {
//                $this->user->shop_cover_path = $this->uploader->upload(
//                    $data['shop_cover'],
//                    'public',
//                    'shop_covers',
//                    $this->user->shop_cover_path
//                );
//                unset($data['shop_cover']);
//            }

            if (isset($data['warehouse_addresses'])) {
                $this->updateWarehouseAddresses($data['warehouse_addresses']);
                unset($data['warehouse_addresses']);
            }

            $this->user->update($data);

            DB::commit();

            return ['user' => $this->user->fresh()];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function updateWarehouseAddresses(array $addresses): void
    {
        foreach ($addresses as $address) {
            if (!isset($address['address'])) {
                throw new InvalidArgumentException("The required field 'address' is missing");
            }
        }

        $newIds = array_filter(array_column($addresses, 'id'), fn($id) => $id !== null);

        $this->user->warehouseAddresses()
            ->whereNotIn('id', $newIds)
            ->delete();

        $this->user->warehouseAddresses()->upsert(
            array_map(fn($addr) => [
                'id' => $addr['id'] ?? null,
                'address' => $addr['address'],
                'is_default' => $addr['is_default'] ?? false,
                'user_id' => $this->user->id
            ], $addresses),
            ['id'],
            ['address', 'is_default']
        );
    }
}
