<?php

namespace App\Http\Controllers\API\v1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UserReportRequest;
use App\Models\User;
use App\Services\Contracts\ProfileServiceInterface;
use App\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ProfileServiceInterface $profileService,
        protected UserService $userService
    ) {}

    public function show(User $user, Request $request): JsonResponse
    {
        $result = $this->userService->getUserData($user, $request);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], $result['status_code']);
        }

        return response()->json($result['data']);
    }

    public function update(ProfileUpdateRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        try {
            $result = $this->profileService->update($request->validated());
            Cache::forget("user_auth:$user->id");
            return response()->json(['data' => $result]);
        } catch (Throwable $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        try {
            DB::transaction(fn() => $user->delete());
            return response()->json(['message' => 'Account successfully deleted']);
        } catch (Throwable $e) {
            Log::error('Account deletion error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function userStories(User $user): JsonResponse
    {
        $stories = $user->stories()
            ->where('created_at', '>', now()->subDay())
            ->paginate(20);

        return response()->json($stories);
    }

    public function getUsersWithStories(Request $request): JsonResponse
    {
        $result = $this->userService->getUsersWithStories($request);

        return response()->json($result['data'], ['message' => $result['message']]);
    }

    public function search(Request $request): JsonResponse
    {
        $result = $this->userService->searchUsers($request);

        return response()->json($result['data']);
    }

    public function report(UserReportRequest $request, User $user): JsonResponse
    {
        $result = $this->userService->reportUser($request->validated(), $user);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], $result['status_code']);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function block(User $user): JsonResponse
    {
        $result = $this->userService->blockUser($user);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], $result['status_code']);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function unblock(User $user): JsonResponse
    {
        $result = $this->userService->unblockUser($user);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], $result['status_code']);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function checkBlocked(User $user, Request $request): JsonResponse
    {
        $isBlocked = $this->userService->isUserBlocked($user, $request);

        return response()->json(['is_blocked' => $isBlocked]);
    }

    public function getBlockedUsers(Request $request): JsonResponse
    {
        $result = $this->userService->getBlockedUsers($request);

        return response()->json(['data' => $result['data']]);
    }

    public function getStatus(User $user): JsonResponse
    {
        $result = $this->userService->getUserStatus($user);

        return response()->json($result['data']);
    }

    public function checkProductPurchase(Request $request, int $productId): JsonResponse
    {
        $hasPurchased = $this->userService->checkProductPurchase($request, $productId);

        return response()->json(['hasPurchased' => $hasPurchased]);
    }

    public function getWarehouseAddresses(Request $request): JsonResponse
    {
        $result = $this->userService->getWarehouseAddresses($request);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], $result['status_code']);
        }

        return response()->json($result['data']);
    }
}
