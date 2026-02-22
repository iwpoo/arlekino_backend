<?php

namespace App\Http\Controllers\API\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->authenticate(
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'user' => $result['user'],
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token']
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');

        if (!$refreshToken) {
            return response()->json(['message' => 'Refresh token missing'], 401);
        }

        try {
            $result = $this->authService->refreshAccessToken($refreshToken);

            return response()->json([
                'access_token' => $result['access_token']
            ]);
        } catch (Exception) {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('warehouseAddresses');

        return response()->json([
            'user' => $user
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Успешный выход']);
    }

    public function verifyEmail(string $id, string $hash): JsonResponse
    {
        $this->authService->verifyEmail($id, $hash);
        return response()->json(['message' => 'Адрес электронной почты успешно подтвержден']);
    }

    public function sendEmailVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Адрес электронной почты уже подтвержден']);
        }

        $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Ссылка для подтверждения отправлена']);
    }
}
