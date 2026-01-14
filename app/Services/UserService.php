<?php

namespace App\Services;

use App\Models\User;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\UserReport;
use App\Models\UserBlock;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserService
{
    public function getUserData(User $user, Request $request): array
    {
        if (!$user->exists) {
            return ['success' => false, 'error' => 'User not found', 'status_code' => 404];
        }

        $currentUser = $request->user();

        if ($currentUser && $this->isBlocked($currentUser->id, $user->id)) {
            return ['success' => false, 'error' => 'User not found', 'status_code' => 404];
        }

        $cacheKey = "user_profile_$user->id";
        $data = Cache::remember($cacheKey, 300, function() use ($user) {
            $user->loadCount(['followers', 'following', 'posts', 'favoriteProducts']);
            $user->load([
                'posts.files',
                'products' => fn($q) => $q->with(['files', 'promotions'])
            ]);

            $user->products->each(function ($product) {
                $product->best_promotion = $product->getBestPromotion();
            });

            return $user;
        });

        return ['success' => true, 'data' => $data];
    }

    public function getUsersWithStories(Request $request): array
    {
        $currentUser = $request->user();
        $yesterday = now()->subDay();

        $users = User::where('id', $currentUser->id)
            ->orWhereHas('followers', fn($q) => $q->where('follower_id', $currentUser->id))
            ->with(['stories' => function ($query) use ($yesterday) {
                $query->where('created_at', '>', $yesterday)
                    ->select('id', 'user_id', 'file_path', 'file_type', 'created_at');
            }])
            ->withCount(['stories as unseen_stories_count' => function ($query) use ($currentUser, $yesterday) {
                $query->where('created_at', '>', $yesterday)
                    ->whereDoesntHave('views', fn($q) => $q->where('user_id', $currentUser->id));
            }])
            ->whereHas('stories', fn($q) => $q->where('created_at', '>', $yesterday))
            ->paginate(15)
            ->filter(fn($u) => $u->id === $currentUser->id || $u->stories->isNotEmpty());

        return [
            'success' => true,
            'data' => $users->values()->all(),
            'message' => 'Success'
        ];
    }

    public function searchUsers(Request $request): array
    {
        $query = trim($request->input('q', ''));
        if (strlen($query) < 2) return ['success' => true, 'data' => []];

        $currentUser = auth()->user();

        $blockedIds = Cache::remember("user_blocks_$currentUser->id", 600, function() use ($currentUser) {
            return UserBlock::where('blocker_id', $currentUser->id)
                ->orWhere('blocked_id', $currentUser->id)
                ->pluck('blocker_id', 'blocked_id')
                ->toArray();
        });

        $users = User::where('id', '!=', $currentUser->id)
            ->whereNotIn('id', $blockedIds)
            ->where(function ($q) use ($query) {
                $q->where('username', 'like', "$query%")
                ->orWhere('name', 'like', "%$query%");
            })
            ->select('id', 'name', 'username', 'avatar_path')
            ->limit(20)
            ->get();

        return ['success' => true, 'data' => $users];
    }

    public function reportUser(array $validatedData, User $user): array
    {
        $currentUser = auth()->user();

        if ($currentUser->id === $user->id) {
            return [
                'success' => false,
                'error' => 'You cannot report yourself',
                'status_code' => 400
            ];
        }

        try {
            $existingReport = UserReport::where('reporter_id', $currentUser->id)
                ->where('reported_id', $user->id)
                ->first();

            if ($existingReport) {
                $existingReport->update(['reason' => $validatedData['reason']]);
                return [
                    'success' => true,
                    'message' => 'Report updated successfully'
                ];
            }

            UserReport::create([
                'reporter_id' => $currentUser->id,
                'reported_id' => $user->id,
                'reason' => $validatedData['reason'],
            ]);

            Log::info("User $currentUser->id reported user $user->id");

            return [
                'success' => true,
                'message' => 'User reported successfully'
            ];
        } catch (Exception $e) {
            Log::error('Report error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to report user',
                'status_code' => 500
            ];
        }
    }

    public function blockUser(User $user): array
    {
        $currentUser = auth()->user();
        if ($currentUser->id === $user->id) return ['success' => false, 'error' => 'Self-block', 'status_code' => 400];

        try {
            DB::transaction(function () use ($currentUser, $user) {
                UserBlock::firstOrCreate([
                    'blocker_id' => $currentUser->id,
                    'blocked_id' => $user->id,
                ]);

                DB::table('follows')
                    ->where(fn($q) => $q->where('follower_id', $currentUser->id)->where('following_id', $user->id))
                    ->orWhere(fn($q) => $q->where('follower_id', $user->id)->where('following_id', $currentUser->id))
                    ->delete();
            });

            Cache::forget("user_blocks_$currentUser->id");
            Cache::forget("block_check_{$currentUser->id}_$user->id");
            Cache::forget("block_check_{$user->id}_$currentUser->id");

            return ['success' => true, 'message' => 'User blocked'];
        } catch (Throwable $e) {
            Log::error('Block error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Server error', 'status_code' => 500];
        }
    }

    public function unblockUser(User $user): array
    {
        $currentUser = auth()->user();

        try {
            $block = UserBlock::where('blocker_id', $currentUser->id)
                ->where('blocked_id', $user->id)
                ->first();

            if (!$block) {
                return [
                    'success' => false,
                    'error' => 'User is not blocked',
                    'status_code' => 404
                ];
            }

            $block->delete();

            Log::info("User $currentUser->id unblocked user $user->id");

            return [
                'success' => true,
                'message' => 'User unblocked successfully'
            ];
        } catch (Exception $e) {
            Log::error('Unblock error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to unblock user',
                'status_code' => 500
            ];
        }
    }

    public function isUserBlocked(User $user, Request $request): bool
    {
        $currentUser = $request->user();

        if (!$currentUser) {
            return false;
        }

        return UserBlock::where('blocker_id', $currentUser->id)
            ->where('blocked_id', $user->id)
            ->exists();
    }

    public function getBlockedUsers(Request $request): array
    {
        $currentUser = $request->user();

        $blockedUsers = UserBlock::where('blocker_id', $currentUser->id)
            ->with(['blocked:id,name,username,avatar_path'])
            ->latest()
            ->paginate(20);

        $blockedUsers->getCollection()->transform(function($block) {
            return [
                'id' => $block->blocked_id,
                'name' => $block->blocked->name,
                'username' => $block->blocked->username,
                'avatar_url' => $block->blocked->avatar_url,
                'blocked_at' => $block->created_at,
            ];
        });

        return [
            'success' => true,
            'data' => $blockedUsers
        ];
    }

    public function getUserStatus(User $user): array
    {
        $isOnline = Cache::has("user-is-online-$user->id");

        if ($isOnline) {
            return [
                'success' => true,
                'data' => [
                    'is_online' => true,
                    'last_activity' => now()->toIso8601String(),
                ]
            ];
        }

        $lastActivity = $user->tokens()
            ->whereNotNull('last_used_at')
            ->max('last_used_at');

        return [
            'success' => true,
            'data' => [
                'is_online' => false,
                'last_activity' => $lastActivity ? $lastActivity->toIso8601String() : null,
            ]
        ];
    }

    public function checkProductPurchase(Request $request, int $productId): bool
    {
        $user = $request->user();

        if (!$user || $user->role !== UserRole::CLIENT->value) {
            return false;
        }

        $product = Product::find($productId);
        if (!$product) {
            return false;
        }

        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', $user->id)
            ->where('order_items.product_id', $productId)
            ->where('orders.status', 'completed')
            ->exists();
    }

    public function getWarehouseAddresses(Request $request): array
    {
        Log::info('getWarehouseAddresses called');
        $user = $request->user();

        Log::info('Fetching warehouse addresses for user: ' . ($user ? $user->id : 'null') . ', role: ' . ($user ? $user->role : 'null'));

        if (!$user) {
            Log::warning('User not authenticated when fetching warehouse addresses');
            return [
                'success' => false,
                'error' => 'Authentication required',
                'status_code' => 401
            ];
        }

        if ($user->role !== UserRole::SELLER->value) {
            Log::warning('User is not a seller when fetching warehouse addresses', ['user_id' => $user->id, 'role' => $user->role]);
            return [
                'success' => false,
                'error' => 'Access denied',
                'status_code' => 403
            ];
        }

        $warehouseAddresses = $user->warehouseAddresses()->get();
        Log::info('Found warehouse addresses: ' . $warehouseAddresses->count(), ['user_id' => $user->id]);

        $warehouses = $warehouseAddresses->map(function ($warehouse) {
            $address = $warehouse->address;
            $addressParts = array_filter(array_map('trim', explode(',', $address)), function ($part) {
                return !empty($part);
            });

            $city = '';
            $country = '';

            if (count($addressParts) >= 2) {
                $city = $addressParts[count($addressParts) - 2];
                $country = $addressParts[count($addressParts) - 1];
            } elseif (count($addressParts) == 1) {
                $city = $addressParts[0];
            }

            $name = $city ? "Склад в $city" : 'Склад';

            return [
                'id' => $warehouse->id,
                'name' => $name,
                'location' => $address,
                'city' => $city,
                'country' => $country
            ];
        });

        Log::info('Returning warehouse addresses', ['count' => $warehouses->count(), 'user_id' => $user->id, 'warehouses' => $warehouses]);

        return [
            'success' => true,
            'data' => [
                'stores' => $warehouses
            ]
        ];
    }

    private function isBlocked(int $userId1, int $userId2): bool
    {
        $cacheKey = "block_check_{$userId1}_$userId2";
        return Cache::remember($cacheKey, 600, function() use ($userId1, $userId2) {
            return UserBlock::where(function($q) use ($userId1, $userId2) {
                $q->where('blocker_id', $userId1)->where('blocked_id', $userId2);
            })->orWhere(function($q) use ($userId1, $userId2) {
                $q->where('blocker_id', $userId2)->where('blocked_id', $userId1);
            })->exists();
        });
    }
}
