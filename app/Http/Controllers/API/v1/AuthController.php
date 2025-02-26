<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        $token = TokenService::storeToken($user);

        return response()->json(['token' => $token]);
    }

//    public function refreshToken(Request $request): JsonResponse
//    {
//        $token = TokenService::getTokenFromDb($request->bearerToken());
//
//        if (!$token) {
//            return response()->json(['message' => 'Unauthorized'], 401);
//        }
//
//        if (TokenService::isExpired($token)) {
//            $newToken = TokenService::updateToken($token);
//
//            if ($newToken) {
//                return response()->json(['token' => $newToken]);
//            }
//        }
//
//        return response()->json(['message' => 'Token not expired'], 401);
//    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        TokenService::deleteToken($user);

        return response()->json(['message' => 'Successfully logged out']);
    }
}
