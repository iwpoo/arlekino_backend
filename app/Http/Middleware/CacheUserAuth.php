<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheUserAuth
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $cacheKey = "user_auth:$user->id";

            $cachedUser = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user) {
                return $user;
            });

            $request->setUserResolver(fn() => $cachedUser);
        }

        return $next($request);
    }
}
