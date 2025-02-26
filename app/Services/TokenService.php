<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class TokenService
{
    protected const ACCESS_TOKEN_TTL = 900; // 15 минут
    protected const REFRESH_TOKEN_TTL = 7; // 7 дней
    protected const TOKEN_PREFIX = 'user_token_';

    public static function storeToken(?User $user): ?string
    {
        $user->tokens()->delete();
        $token = $user->createToken('auth_token', ['*'], now()->addDays(self::REFRESH_TOKEN_TTL))->plainTextToken;

        Redis::setex(self::TOKEN_PREFIX . $user->id, self::ACCESS_TOKEN_TTL, $token);

        return $token;
    }

    /**
     * Получаем токен из Redis.
     *
     * @param int|null $userId
     * @return string|null
     */
    public static function getTokenFromRedis(?int $userId): ?string
    {
        return Redis::get(self::TOKEN_PREFIX . $userId);
    }

    /**
     * Получаем токен из БД.
     *
     * @param string|null $token
     * @return PersonalAccessToken|null
     */
    public static function getTokenFromDb(?string $token): ?PersonalAccessToken
    {
        return PersonalAccessToken::findToken($token);
    }

    /**
     * Обновляем токен в Redis и БД.
     *
     * @param PersonalAccessToken|null $oldToken
     * @return string|null
     */
    public static function updateToken(?PersonalAccessToken $oldToken): ?string
    {
        $user = $oldToken->tokenable;
        $newToken = $user->createToken('auth_token', ['*'], now()->addDays(self::REFRESH_TOKEN_TTL))->plainTextToken;

        Redis::setex(self::TOKEN_PREFIX . $user->id, self::ACCESS_TOKEN_TTL, $newToken);

        return $newToken;
    }

    /**
     * @param User $user
     * @param string $token
     * @return void
     */
    public static function updateTokenInRedis(User $user, string $token): void
    {
        Redis::setex(self::TOKEN_PREFIX . $user->id, self::ACCESS_TOKEN_TTL, $token);
    }

    /**
     * Удаляем токен из Redis и БД.
     *
     * @param User $user
     */
    public static function deleteToken(User $user): void
    {
        Redis::del(self::TOKEN_PREFIX . $user->id);
        $user->tokens()->delete();
    }

    public static function isExpired(PersonalAccessToken $token): bool
    {
        return $token->expires_at && Carbon::parse($token->expires_at)->isPast();
    }
}
