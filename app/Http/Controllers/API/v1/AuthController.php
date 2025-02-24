<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('App Token')->plainTextToken;

        Redis::setex("sanctum_token_{$user->id}", 3600, $token);

        return response()->json(['token' => $token], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = $user->createToken('App Token')->plainTextToken;

        Redis::setex("sanctum_token_{$user->id}", 3600, $token);

        return response()->json(['token' => $token]);
    }

    public function checkToken(Request $request)
    {
        $token = $request->bearerToken();
        $userId = $this->getUserIdFromToken($token);

        $cachedToken = Redis::get("sanctum_token_{$userId}");

        if ($cachedToken && $cachedToken == $token) {
            return response()->json(['message' => 'Token is valid']);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken && $accessToken->isValid()) {
            Redis::setex("sanctum_token_{$userId}", 3600, $token);
            return response()->json(['message' => 'Token is valid']);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    private function getUserIdFromToken($token)
    {
        $accessToken = PersonalAccessToken::findToken($token);
        return $accessToken ? $accessToken->tokenable->id : null;
    }
}
