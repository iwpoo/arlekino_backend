<?php

namespace App\Http\Controllers\API\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
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

        return response()->json($result);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Выход из системы пройден успешно']);
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
