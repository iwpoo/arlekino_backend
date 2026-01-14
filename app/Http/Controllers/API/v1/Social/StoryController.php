<?php

namespace App\Http\Controllers\API\v1\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoryCreateRequest;
use App\Jobs\ProcessStoryFile;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $page = $request->query('page', 1);

        $cacheKey = "user_feed_stories_{$userId}_p$page";

        return Cache::remember($cacheKey, 300, function () use ($userId) {
            return Story::query()
                ->with('user')
                ->whereIn('user_id', function ($query) use ($userId) {
                    $query->select('following_id')
                        ->from('follows')
                        ->where('follower_id', $userId);
                })
                ->where('created_at', '>=', now()->subHours(24))
                ->latest()
                ->simplePaginate(15);
        });
    }

    public function store(StoryCreateRequest $request): JsonResponse
    {
        $user = $request->user();

        $tempPath = $request->file('file')->store('temp/stories', 'public');

        $story = Story::create([
            'user_id' => $user->id,
            'file_path' => $tempPath,
            'file_type' => strtok($request->file('file')->getClientMimeType(), '/'),
            'is_ready' => false,
        ]);

        ProcessStoryFile::dispatch($story);

        return response()->json([
            'message' => 'Story created',
            'story' => $story
        ], 201);
    }

    public function show(Story $story): JsonResponse
    {
        return response()->json($story);
    }

    public function destroy(Request $request, Story $story): JsonResponse
    {
        if ($story->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Storage::disk('public')->delete($story->file_path);
        $story->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function markAsViewed(Story $story, Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $cacheKey = "user_{$userId}_viewed_story_$story->id";

        if (!Cache::has($cacheKey)) {
            $story->views()->firstOrCreate(['user_id' => $userId]);

            Cache::put($cacheKey, true, now()->addHours(24));
        }

        return response()->json(['message' => 'Viewed']);
    }
}
