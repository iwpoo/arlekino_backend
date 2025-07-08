<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\Contracts\ProfileServiceInterface;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserController extends Controller
{
    /**
     * @param ProfileServiceInterface $profileService
     */
    public function __construct(
        protected ProfileServiceInterface $profileService
    ) {}

    /**
     * Display the specified resource.
     */
    public function show(User $user): JsonResponse
    {
        $user->loadCount(['followers', 'following', 'posts']);

        $user->load(['posts.files', 'products.files']);

        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProfileUpdateRequest $request, User $user): JsonResponse
    {
        if (auth()->id() !== $user->id) {
            return response()->json(['error' => 'No access'], 403);
        }

        try {
            $result = $this->profileService->update($request->validated());
            return response()->json([
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * @throws Throwable
     */
    public function destroy(User $user): JsonResponse
    {
        DB::beginTransaction();

        try {
            if (auth()->id() !== $user->id) {
                return response()->json(['error' => 'No access'], 403);
            }

            $user->delete();

            DB::commit();

            Log::info("User account $user->id has been deleted");
            return response()->json(['message' => 'Account successfully deleted']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Account deletion error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function userStories(User $user): JsonResponse
    {
        return response()->json($user->stories()->get());
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
