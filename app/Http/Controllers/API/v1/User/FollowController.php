<?php

namespace App\Http\Controllers\API\v1\User;

use App\Events\SocialActivity;
use App\Http\Controllers\Controller;
use App\Jobs\UpdateUserStatsJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FollowController extends Controller
{
    public function store(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser->id === $user->id) {
            return response()->json(['message' => 'You cannot subscribe to yourself'], 422);
        }

        $changes = $authUser->following()->syncWithoutDetaching([$user->id]);

        if (count($changes['attached']) > 0) {
            UpdateUserStatsJob::dispatch($user->id, 'followers_count', 1);

            Cache::forget("is_following:$authUser->id:$user->id");

            if ($user->isSeller()) {
                event(new SocialActivity('follow', $user, $user, $authUser));
            }
        }

        return response()->json([
            'message' => 'Subscribed successfully',
            'followers_count' => $user->followers_count + (count($changes['attached']) > 0 ? 1 : 0),
            'is_following' => true,
        ], 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        $detached = $authUser->following()->detach($user->id);

        if ($detached > 0) {
            UpdateUserStatsJob::dispatch($user->id, 'followers_count', -1);
            Cache::forget("is_following:$authUser->id:$user->id");
        }

        return response()->json([
            'message' => 'Unsubscribed successfully',
            'followers_count' => max(0, $user->followers_count - ($detached > 0 ? 1 : 0)),
            'is_following' => false,
        ]);
    }

    public function count(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();
        $isFollowing = false;

        if ($authUser) {
            $isFollowing = Cache::remember(
                "is_following:$authUser->id:$user->id",
                now()->addHours(),
                fn() => $authUser->following()->where('following_id', $user->id)->exists()
            );
        }

        return response()->json([
            'followers_count' => $user->followers_count,
            'is_following' => $isFollowing,
        ]);
    }
}


