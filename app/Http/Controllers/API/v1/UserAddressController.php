<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    public function index(User $user): JsonResponse
    {
        return response()->json($user->addresses()->get());
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'country' => 'required|string|max:100',
            'region' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'street' => 'required|string|max:255',
            'house' => 'required|string|max:20',
            'apartment' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:20',
            'is_default' => 'boolean'
        ]);

        // Если это первый адрес, делаем его по умолчанию
        $isDefault = $request->is_default ?? ($user->addresses()->count() === 0);

        $address = $user->addresses()->create([
            'country' => $request->country,
            'region' => $request->region,
            'city' => $request->city,
            'street' => $request->street,
            'house' => $request->house,
            'apartment' => $request->apartment,
            'postal_code' => $request->postal_code,
            'is_default' => $isDefault
        ]);

        return response()->json($address, 201);
    }

    public function update(Request $request, User $user, UserAddress $address): JsonResponse
    {
        $request->validate([
            'country' => 'string|max:100',
            'region' => 'nullable|string|max:100',
            'city' => 'string|max:100',
            'street' => 'string|max:255',
            'house' => 'string|max:20',
            'apartment' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:20',
            'is_default' => 'boolean'
        ]);

        $address->update($request->all());

        return response()->json($address);
    }

    public function destroy(User $user, UserAddress $address): JsonResponse
    {
        $address->delete();

        return response()->json(['message' => 'User address deleted successfully']);
    }

    public function setDefault(User $user, UserAddress $address): JsonResponse
    {
        $user->addresses()->update(['is_default' => false]);

        $address->update(['is_default' => true]);

        return response()->json($address);
    }
}
