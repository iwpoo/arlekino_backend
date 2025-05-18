<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\Story;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $perPage = $request->query('per_page', 10);

        $followingIds = Follow::where('follower_id', $userId)
            ->pluck('following_id');

        $stories = Story::whereIn('user_id', $followingIds)
            ->with(['user'])
            ->orderByDesc('stories.created_at')
            ->paginate($perPage);

        return response()->json($stories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,mp4',
        ]);

        DB::beginTransaction();

        try {
            $path = $request->file('file')->store('story_files', 'public');

            $story = Story::create([
                'user_id' => $request->user()->id,
                'file_path' => $path,
                'file_type' => strtok($request->file('file')->getClientMimeType(), '/'),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Story created successfully',
                'story_id' => $story->id,
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create story',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Story $story): JsonResponse
    {
        return response()->json($story);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Story $story): JsonResponse
    {
        if ($story->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $story->delete();

        return response()->json(['message' => 'Story deleted successfully']);
    }

    public function markAsViewed(Story $story, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$story->views()->where('user_id', $user->id)->exists()) {
            $story->views()->create(['user_id' => $user->id]);
        }

        return response()->json(['message' => 'Story viewed']);
    }
}
