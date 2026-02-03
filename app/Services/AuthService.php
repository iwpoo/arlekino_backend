<?php

namespace App\Services;

use App\Jobs\TrackUserDeviceJob;
use App\Models\User;
use Illuminate\Support\Facades\{Hash, Cache, Log, RateLimiter, DB};
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;
use Throwable;

class AuthService
{
    public function __construct(protected TwilioService $twilioService) {}

    public function authenticate(array $data, string $ip, string $userAgent): array
    {
        $login = $data['login'];
        $throttleKey = 'login:' . Str::slug($login);

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('services.auth.login_attempts_limit', 5))) {
            throw ValidationException::withMessages([
                'login' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($field, $login)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, (int) config('services.auth.login_timeout_seconds', 600));
            throw ValidationException::withMessages(['login' => 'Неверные учетные данные.']);
        }

        RateLimiter::clear($throttleKey);

        Cache::forget("user_auth:$user->id");

        TrackUserDeviceJob::dispatch($user, $ip, $userAgent);

        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(15))->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

        return [
            'user'  => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $tokenModel = PersonalAccessToken::findToken($refreshToken);

        if (!$tokenModel ||
            $tokenModel->expires_at?->isPast() ||
            !$tokenModel->can('issue-access-token')) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Сессия истекла, войдите заново.']
            ]);
        }

        $user = $tokenModel->tokenable;

        $user->tokens()->where('name', 'access_token')->delete();

        $newAccessToken = $user->createToken(
            'access_token',
            ['*'],
            now()->addMinutes(config('services.auth.access_token_lifespan', 15))
        )->plainTextToken;

        return ['access_token' => $newAccessToken];
    }

    public function sendPhoneCode(string $phone): void
    {
        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'Этот номер уже зарегистрирован.']);
        }

        $rateKey = "phone_code:$phone";
        if (RateLimiter::tooManyAttempts($rateKey, (int) config('services.auth.phone_verification_attempts_limit', 3))) {
            throw ValidationException::withMessages(['phone' => 'Слишком много попыток.']);
        }

        RateLimiter::hit($rateKey, (int) config('services.auth.phone_verification_timeout_seconds', 3600));
        $this->twilioService->sendVerificationCodeQueue($phone);
    }

    public function verifyPhoneCode(string $phone, string $code): array
    {
        $isValid = $this->twilioService->verifyCode($phone, $code);

        if (!$isValid) {
            throw ValidationException::withMessages(['code' => 'Неверный или просроченный код.']);
        }

        RateLimiter::clear("phone_code:$phone");
        $user = User::where('phone', $phone)->first();

        if ($user) {
            $user->tokens()->delete();

            $user->tokens()->create([
                'name' => 'access_token',
                'token' => hash('sha256', $atPlain = Str::random(40)),
                'abilities' => ['*'],
                'expires_at' => now()->addMinutes(15),
            ]);

            $user->tokens()->create([
                'name' => 'refresh_token',
                'token' => hash('sha256', $rtPlain = Str::random(40)),
                'abilities' => ['issue-access-token'],
                'expires_at' => now()->addDays(30),
            ]);

            return [
                'type'          => 'login',
                'user'          => $user,
                'access_token'  => $atPlain,
                'refresh_token' => $rtPlain,
            ];
        }

        $tempToken = Str::random(60);
        Cache::put("reg_token:$tempToken", $phone, now()->addHour());

        return [
            'type'       => 'register',
            'temp_token' => $tempToken
        ];
    }

    public function registerWithPhone(array $data, string $ip, string $userAgent): array
    {
        $phone = Cache::pull("reg_token:{$data['temp_token']}");

        if (!$phone) {
            throw ValidationException::withMessages(['temp_token' => 'Токен истек.']);
        }

        try {
            $user = DB::transaction(fn() => User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $phone,
                'password' => Hash::make($data['password']),
                'role' => $data['role'] ?? 'client',
                'email' => $data['email'] ?? null,
            ]));

            TrackUserDeviceJob::dispatch($user, $ip, $userAgent);

            $user->tokens()->create([
                'name' => 'access_token',
                'token' => hash('sha256', $atPlain = Str::random(40)),
                'abilities' => ['*'],
                'expires_at' => now()->addMinutes(15),
            ]);

            $user->tokens()->create([
                'name' => 'refresh_token',
                'token' => hash('sha256', $rtPlain = Str::random(40)),
                'abilities' => ['issue-access-token'],
                'expires_at' => now()->addDays(30),
            ]);

            return [
                'user'          => $user,
                'access_token'  => $atPlain,
                'refresh_token' => $rtPlain,
            ];
        } catch (QueryException $e) {
            Log::error("Registration DB Error: " . $e->getMessage());

            throw ValidationException::withMessages([
                'username' => ['Этот логин уже занят или произошла ошибка базы данных']
            ]);
        } catch (Throwable $e) {
            Log::critical("Registration Critical Error: " . $e->getMessage(), [
                'phone' => $phone,
                'data'  => collect($data)->except('password')->toArray()
            ]);

            throw new RuntimeException("Не удалось завершить регистрацию. Попробуйте позже.", 500);
        }
    }

    public function verifyEmail(string $id, string $hash): void
    {
        $user = User::findOrFail($id);

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            throw ValidationException::withMessages(['email' => 'Невалидная ссылка верификации.']);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
    }
}
