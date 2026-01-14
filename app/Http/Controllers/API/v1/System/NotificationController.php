<?php

namespace App\Http\Controllers\API\v1\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->simplePaginate(20);

        $unreadCounts = Cache::remember("user_notif_counts:$user->id", 60, function() use ($user) {
            return [
                'total' => $user->unreadNotifications()->count(),
                'order' => $user->unreadNotifications()->where('data->type', 'order')->count()
            ];
        });

        return response()->json([
            'notifications' => $notifications->items(),
            'unread_count' => $unreadCounts['total'],
            'unread_order_count' => $unreadCounts['order'],
            'has_more' => $notifications->hasMorePages(),
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $affected = $request->user()
            ->unreadNotifications()
            ->where('id', $id)
            ->update(['read_at' => now()]);

        if ($affected) {
            Cache::forget("user_notif_counts:{$request->user()->id}");
        }

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->unreadNotifications()->update(['read_at' => now()]);

        Cache::forget("user_notif_counts:$user->id");

        return response()->json([
            'success' => true,
            'unread_count' => 0,
            'unread_order_count' => 0
        ]);
    }
}
