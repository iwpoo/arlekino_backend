<?php

namespace App\Services;

use App\Mail\User\ConfirmationMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Random\RandomException;
use RuntimeException;

class EmailVerificationService
{
    private const CODE_EXPIRATION = 3600; // 1 час

    public function sendRegistrationCode(string $email): void
    {
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Этот email уже зарегистрирован.']);
        }

        $this->generateAndSendCode($email, $this->getCacheKey('reg', $email));
    }

    public function verifyRegistrationCode(string $email, string $code): bool
    {
        $key = $this->getCacheKey('reg', $email);

        if (Cache::get($key) !== $code) {
            throw ValidationException::withMessages(['code' => 'Неверный код подтверждения.']);
        }

        Cache::forget($key);
        return true;
    }

    public function sendAttachmentCode(User $user, string $email): void
    {
        $isTaken = User::where('email', $email)->where('id', '!=', $user->id)->exists();

        if ($isTaken) {
            throw ValidationException::withMessages(['email' => 'Этот email уже занят другим пользователем.']);
        }

        $this->generateAndSendCode($email, $this->getCacheKey('attach', $email, $user->id));
    }

    public function verifyAndAttachEmail(User $user, string $email, string $code): User
    {
        $key = $this->getCacheKey('attach', $email, $user->id);

        if (Cache::get($key) !== $code) {
            throw ValidationException::withMessages(['code' => 'Неверный код подтверждения.']);
        }

        Cache::forget($key);

        $user->update([
            'email' => $email,
            'email_verified_at' => now()
        ]);

        return $user;
    }

    private function generateAndSendCode(string $email, string $cacheKey): void
    {
        try {
            $code = (string) random_int(100000, 999999);
        } catch (RandomException $e) {
            Log::critical('Ошибка генерации случайного числа: ' . $e->getMessage());
            throw new RuntimeException('Сервис временно недоступен', 500);
        }

        Cache::put($cacheKey, $code, self::CODE_EXPIRATION);

        Mail::to($email)->queue(new ConfirmationMail($code, $email));
    }

    private function getCacheKey(string $type, string $email, ?int $userId = null): string
    {
        return "email_{$type}_" . md5($email . $userId);
    }
}
