<?php

namespace App\Http\Controllers\API\v1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()->id;

        $cacheKey = "user_sessions_$user->id";

        $sessions = Cache::remember($cacheKey, 60, function () use ($user, $currentId) {
            return $user->tokens()
                ->latest()
                ->get()
                ->map(function ($token) use ($currentId) {
                    return [
                        'id'         => $token->id,
                        'name'       => $token->name,
                        'last_used'  => $token->last_used_at ? $token->last_used_at->toDateTimeString() : null,
                        'created_at' => $token->created_at->toDateTimeString(),
                        'is_current' => $token->id === $currentId,
                    ];
                });
        });

        return response()->json([
            'sessions' => $sessions,
            'authorized_devices' => $user->authorized_devices ?? []
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $token = $user->tokens()->where('id', $id)->first();

        if (!$token) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $token->delete();

        Cache::forget("user_sessions_$user->id");

        return response()->json(['message' => 'Session terminated successfully']);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentThreadId = $user->currentAccessToken()->id;

        $user->tokens()->where('id', '!=', $currentThreadId)->delete();

        Cache::forget("user_sessions_$user->id");

        return response()->json(['message' => 'Other sessions terminated']);
    }
}
