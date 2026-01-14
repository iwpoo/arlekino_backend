<?php

namespace App\Http\Controllers\API\v1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserAddressCreateRequest;
use App\Http\Requests\UserAddressUpdateRequest;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserAddressController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $addresses = Cache::remember("user_addresses_$userId", 3600, function () {
            return Auth::user()->addresses()->latest()->get();
        });

        return response()->json($addresses);
    }

    public function store(UserAddressCreateRequest $request): JsonResponse
    {
        $user = Auth::user();
        $hasAddresses = $user->addresses()->exists();
        $isDefault = $request->boolean('is_default') || !$hasAddresses;

        $address = $user->addresses()->create($request->validated() + ['is_default' => $isDefault]);

        if ($isDefault) {
            $this->updateDefaultAddress($address);
        }

        $this->clearAddressCache($user->id);
        return response()->json($address, 201);
    }

    public function update(UserAddressUpdateRequest $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== Auth::id()) abort(403);

        try {
            return DB::transaction(function () use ($request, $address) {
                $address->update($request->validated());

                if ($request->boolean('is_default')) {
                    $this->updateDefaultAddress($address);
                }

                $this->clearAddressCache($address->user_id);
                return response()->json($address);
            });
        } catch (Throwable $e) {
            Log::error("Address Update Error [ID: $address->id]: " . $e->getMessage());

            return response()->json([
                'message' => 'Не удалось обновить адрес. Попробуйте позже.'
            ], 500);
        }
    }

    public function destroy(UserAddress $address): JsonResponse
    {
        if ($address->user_id !== Auth::id()) abort(403);

        try {
            DB::transaction(function () use ($address) {
                $userId = $address->user_id;
                $wasDefault = $address->is_default;
                $address->delete();

                if ($wasDefault) {
                    UserAddress::where('user_id', $userId)->first()?->update(['is_default' => true]);
                }

                $this->clearAddressCache($userId);
            });
        } catch (Throwable $e) {
            Log::error("Address Delete Error [ID: $address->id]: " . $e->getMessage());

            return response()->json([
                'message' => 'Ошибка при удалении адреса'
            ], 500);
        }

        return response()->json(['message' => 'Address deleted']);
    }

    public function setDefault(UserAddress $address): JsonResponse
    {
        if ($address->user_id !== Auth::id()) abort(403);

        $this->updateDefaultAddress($address);

        return response()->json(['message' => 'Default address updated', 'address' => $address]);
    }

    private function updateDefaultAddress(UserAddress $address): void
    {
        UserAddress::where('user_id', $address->user_id)
            ->where('id', '!=', $address->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $address->update(['is_default' => true]);
    }

    private function clearAddressCache(int $userId): void
    {
        Cache::forget("user_addresses_$userId");
    }
}
