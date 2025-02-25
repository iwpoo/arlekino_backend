<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
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
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        Redis::setex("token:$token", 900, $user->id);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 600,
        ]);
    }

    public function checkToken(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $userId = $this->getUserIdFromToken($token);

        $cachedToken = Redis::get("sanctum_token_$userId");

        if ($cachedToken && $cachedToken == $token) {
            return response()->json(['message' => 'Token is valid']);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken && $accessToken->isValid()) {
            Redis::setex("sanctum_token_$userId", 3600, $token);
            return response()->json(['message' => 'Token is valid']);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        Redis::del("api_token_$token");

        return response()->json(['message' => 'Successfully logged out']);
    }
}
