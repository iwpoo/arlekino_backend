<?php

namespace App\Http\Controllers\API\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendPasswordResetCodeRequest;
use App\Http\Requests\VerifyPasswordResetCodeRequest;
use App\Mail\User\PasswordResetMail;
use App\Models\User;
use App\Services\TwilioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Random\RandomException;
use RuntimeException;

class PasswordResetController extends Controller
{
    protected TwilioService $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    public function sendResetCode(SendPasswordResetCodeRequest $request): JsonResponse
    {
        $login = $request->input('login');

        $isPhone = preg_match('/^\+[1-9]\d{1,14}$/', $login);

        $user = $isPhone
            ? User::where('phone', $login)->first()
            : User::where('email', $login)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Пользователь не найден с предоставленными учетными данными'
            ], 422);
        }

        try {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (RandomException $e) {
            Log::critical('Ошибка генерации случайного числа: ' . $e->getMessage());
            throw new RuntimeException('Сервис временно недоступен', 500);
        }

        if ($isPhone) {
            $this->twilioService->sendVerificationCodeQueue($login);
        } else {
            $cacheKey = "password_reset_code_$user->id";
            Cache::put($cacheKey, $code, 3600);

            Mail::to($login)->queue(new PasswordResetMail($code, $login));
        }

        return response()->json([
            'message' => 'Код для сброса пароля успешно отправлен',
            'user_id' => $user->id
        ]);
    }

    public function verifyResetCode(VerifyPasswordResetCodeRequest $request): JsonResponse
    {
        $userId = $request->input('user_id');
        $code = $request->input('code');
        $login = $request->input('login');

        $isPhone = preg_match('/^\+[1-9]\d{1,14}$/', $login);

        if ($isPhone) {
            $isValid = $this->twilioService->verifyCode($login, $code);
        } else {
            $errorKey = "password_reset_errors_$userId";
            $attempts = Cache::get($errorKey, 0);

            if ($attempts >= 5) {
                Cache::forget("password_reset_code_$userId");
                return response()->json(['message' => 'Слишком много неудачных попыток. Запросите новый код.'], 422);
            }

            $cachedCode = Cache::get("password_reset_code_$userId");
            $isValid = ($cachedCode && $cachedCode === $code);

            if (!$isValid) {
                Cache::put($errorKey, $attempts + 1, 3600);
            }
        }

        if (!$isValid) {
            return response()->json(['message' => 'Неверный код сброса'], 422);
        }

        Cache::forget("password_reset_code_$userId");
        Cache::forget("password_reset_errors_$userId");

        $token = Str::random(60);
        Cache::put("password_reset_token_$userId", $token, 3600);

        return response()->json([
            'message' => 'Код успешно проверен',
            'token' => $token,
            'user_id' => $userId
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $userId = $request->input('user_id');
        $token = $request->input('token');

        $cacheKeyToken = "password_reset_token_$userId";
        if (Cache::get($cacheKeyToken) !== $token) {
            return response()->json([
                'message' => 'Недействительный или просроченный токен сброса'
            ], 422);
        }

        $user = User::findOrFail($userId);
        $user->update(['password' => Hash::make($request->input('password'))]);

        Cache::forget($cacheKeyToken);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Пароль успешно сброшен'
        ]);
    }
}
