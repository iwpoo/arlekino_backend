<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }

    public function getUsersWithStories(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $usersWithStories = User::whereHas('stories', function($query) {
            $query->where('created_at', '>', now()->subDay());
        })
            ->whereHas('followers', function($query) use ($currentUser) {
                $query->where('follower_id', $currentUser->id);
            })
            ->with(['stories' => function($query) {
                $query->where('created_at', '>', now()->subDay())
                    ->select('id', 'user_id', 'file_path', 'file_type', 'created_at');
            }])
            ->withCount(['stories as unseen_stories_count' => function($query) use ($currentUser) {
                $query->where('created_at', '>', now()->subDay())
                    ->whereDoesntHave('views', function($q) use ($currentUser) {
                        $q->where('user_id', $currentUser->id);
                    });
            }])
            ->get();

        return response()->json([
            'data' => $usersWithStories,
            'message' => 'Users with stories retrieved successfully'
        ]);
    }
}
