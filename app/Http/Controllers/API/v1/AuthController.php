<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|integer|unique:users',
            'password' => 'required|string|min:4',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        User::create([
            'phone' => $request->get('phone'),
            'password' => Hash::make($request->get('password')),
        ]);

        return response()->json(['message' => 'User registered successfully'], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'The provided credentials are incorrect.'], 422);
        }

        $token = $user->createToken('auth_token', [], now()->addMinutes(config('sanctum.expiration')))->plainTextToken;

        return response()->json(compact('token'), 201)->withCookie(
            'token',
            $token,
            config('sanctum.expiration'),
            null,
            null,
            false,
            true
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([$request->user()]);
    }
}
