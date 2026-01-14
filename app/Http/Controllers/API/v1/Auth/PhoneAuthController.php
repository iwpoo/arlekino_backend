<?php

namespace App\Http\Controllers\API\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CodeVerificationRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PhoneRegisterRequest;
use App\Http\Requests\PhoneVerificationRequest;
use App\Services\AuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PhoneAuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function sendVerificationCode(PhoneVerificationRequest $request): JsonResponse
    {
        $this->authService->sendPhoneCode($request->phone);
        return response()->json(['message' => 'Код подтверждения успешно отправлен']);
    }

    public function verifyCode(CodeVerificationRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->verifyPhoneCode($request->phone, $request->code);

            return response()->json($result);
        } catch (Exception $e) {
            Log::error("Verification Controller Error: " . $e->getMessage());

            return response()->json([
                'message' => $e->getCode() === 503 ? $e->getMessage() : 'Произошла непредвиденная ошибка.'
            ], $e->getCode() ?: 500);
        }
    }

    public function register(PhoneRegisterRequest $request): JsonResponse
    {
        $result = $this->authService->registerWithPhone(
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );
        return response()->json($result);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->authenticate(
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json($result);
    }
}
