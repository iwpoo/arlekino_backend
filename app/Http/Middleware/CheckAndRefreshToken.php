<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class CheckAndRefreshToken
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $token = $user->currentAccessToken();

            // Проверяем, истек ли срок действия токена
            if ($token && now()->greaterThanOrEqualTo($token->expires_at)) {
                // Удаляем старый токен
                $token->delete();

                // Генерируем новый токен
                $newToken = $user->createToken('auth_token', [], now()->addMinutes(config('sanctum.expiration')));

                // Отправляем новый токен в куках
                $response = $next($request);
                $response->headers->setCookie(
                    Cookie::make(
                        'token',
                        $newToken->plainTextToken,
                        config('sanctum.expiration'),
                        null,
                        null,
                        false,
                    )
                );

                return $response;
            }
        }

        return $next($request);
    }
}
