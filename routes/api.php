<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\v1\{
    Auth\AuthController,
    Auth\EmailVerificationController,
    Auth\PasswordResetController,
    Auth\PhoneAuthController,
    Chat\ChatController,
    Chat\MessageController,
    Marketing\PromotionController,
    Marketing\RecommendationController,
    Shop\CartController,
    Shop\CategoryController,
    Shop\FavoriteProductController,
    Shop\OrderController,
    Shop\ProductController,
    Shop\ProductQuestionController,
    Shop\PurchaseController,
    Shop\ReturnsController,
    Shop\ReviewController,
    Shop\ReviewCommentController,
    Social\CommentController,
    Social\FavoritePostController,
    Social\LikeController,
    Social\PostController,
    Social\StoryController,
    System\AnalyticsController,
    System\CurrencyController,
    System\DashboardController,
    System\NotificationController,
    System\SearchController,
    User\BankCardController,
    User\FollowController,
    User\SessionController,
    User\UserAddressController,
    User\UserController
};

Route::prefix('v1')->group(static function (): void {

    // --- PUBLIC & AUTH ENTRANCE ---
    Route::prefix('auth')->group(static function (): void {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

        // Password Recovery
        Route::prefix('password')->group(static function (): void {
            Route::post('send-code', [PasswordResetController::class, 'sendResetCode']);
            Route::post('verify-code', [PasswordResetController::class, 'verifyResetCode']);
            Route::post('reset', [PasswordResetController::class, 'resetPassword']);
        });

        // Registration & Verification
        Route::prefix('phone')->group(static function (): void {
            Route::post('send-code', [PhoneAuthController::class, 'sendVerificationCode']);
            Route::post('verify-code', [PhoneAuthController::class, 'verifyCode']);
            Route::post('register', [PhoneAuthController::class, 'register']);
            Route::post('login', [PhoneAuthController::class, 'login']);
        });

        Route::prefix('email')->group(static function (): void {
            Route::post('send-code', [EmailVerificationController::class, 'sendVerificationCode']);
            Route::post('verify-code', [EmailVerificationController::class, 'verifyCode']);
            Route::post('attach/send-code', [EmailVerificationController::class, 'resendEmailAttachmentCode']);
            Route::post('attach/verify-code', [EmailVerificationController::class, 'verifyEmailAttachmentCode']);
        });
    });

    // --- AUTHENTICATED ROUTES ---
    Route::middleware(['auth:sanctum', 'cache-user-auth', 'user-online'])->group(static function (): void {

        // User Profile & Identity
        Route::prefix('auth')->group(static function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('email/verification-notification', [AuthController::class, 'sendEmailVerification']);
        });

        // User Management
        Route::get('users/search', [UserController::class, 'search']);
        Route::get('users/with-story', [UserController::class, 'getUsersWithStories']);
        Route::get('users/blocked/list', [UserController::class, 'getBlockedUsers']);
        Route::apiResource('users', UserController::class)->only(['show', 'update', 'destroy']);

        Route::prefix('users/{user}')->group(static function (): void {
            Route::get('stories', [UserController::class, 'userStories']);
            Route::get('reviews', [ReviewController::class, 'userReviews']);
            Route::get('status', [UserController::class, 'getStatus']);
            Route::get('check-blocked', [UserController::class, 'checkBlocked']);
            Route::post('report', [UserController::class, 'report']);
            Route::post('block', [UserController::class, 'block']);
            Route::delete('block', [UserController::class, 'unblock']);
            Route::post('subscribe', [FollowController::class, 'store']);
            Route::delete('subscribe', [FollowController::class, 'destroy']);
        });

        // Social Content (Posts, Stories)
        Route::apiResource('posts', PostController::class);
        Route::prefix('posts/{post}')->group(static function (): void {
            Route::post('view', [PostController::class, 'incrementViews']);
            Route::post('share', [PostController::class, 'incrementShares']);
            Route::post('report', [PostController::class, 'report']);
            Route::post('like', [LikeController::class, 'like']);
            Route::delete('unlike', [LikeController::class, 'unlike']);
        });
        Route::apiResource('posts.comments', CommentController::class)->shallow();

        Route::apiResource('stories', StoryController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('stories/{story}/view', [StoryController::class, 'markAsViewed']);

        // Marketplace: Products & Catalog
        Route::apiResource('products', ProductController::class);
        Route::prefix('categories')->group(static function (): void {
            Route::get('/', [CategoryController::class, 'getCategoriesWithQuestions']);
            Route::get('{categoryId}/questions', [CategoryController::class, 'getQuestionsByCategory']);
            Route::get('parent/{parentId}/subcategories', [CategoryController::class, 'getSubcategories']);
        });

        // Feedback System (Reviews & Questions)
        Route::get('products/{product}/reviews', [ReviewController::class, 'index']);
        Route::get('reviews/products-without-review', [ReviewController::class, 'getProductsWithoutReview']);
        Route::post('reviews/{review}/helpful', [ReviewController::class, 'markHelpful']);

        Route::get('reviews/{review}/comments', [ReviewCommentController::class, 'index']);
        Route::post('reviews/{review}/comments', [ReviewCommentController::class, 'store']);
        Route::delete('review-comments/{comment}', [ReviewCommentController::class, 'destroy']);
        Route::post('review-comments/{comment}/like', [ReviewCommentController::class, 'toggleLike']);

        Route::get('products/{product}/questions', [ProductQuestionController::class, 'index']);
        Route::post('questions/{question}/helpful', [ProductQuestionController::class, 'markHelpful']);

        // Role: Client Specific
        Route::middleware('role:client')->group(static function (): void {
            Route::post('products/{product}/reviews', [ReviewController::class, 'store']);
            Route::put('reviews/{review}', [ReviewController::class, 'update']);
            Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);

            Route::post('products/{product}/questions', [ProductQuestionController::class, 'store']);
            Route::delete('questions/{question}', [ProductQuestionController::class, 'destroy']);

            Route::get('user/purchased-products/check/{productId}', [UserController::class, 'checkProductPurchase']);
            Route::get('purchases', [PurchaseController::class, 'index']);

            // Cart & Favorites
            Route::apiResource('cart', CartController::class)->except(['show']);
            Route::apiResource('favorites', FavoriteProductController::class)->only(['index', 'store', 'destroy']);
            Route::get('favorites/check/{product}', [FavoriteProductController::class, 'check']);

            // Payments & Addresses
            Route::apiResource('bank-cards', BankCardController::class)->only(['index', 'store', 'destroy']);
            Route::post('bank-cards/{card}/set-default', [BankCardController::class, 'setDefault']);
            Route::apiResource('users.addresses', UserAddressController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::patch('users/{user}/addresses/{address}/set-default', [UserAddressController::class, 'setDefault']);
        });

        // Role: Seller Specific
        Route::middleware('role:seller')->group(static function (): void {
            Route::get('analytics', [AnalyticsController::class, 'index']);
            Route::get('dashboard', [DashboardController::class, 'index']);
            Route::post('dashboard/orders/{sellerOrder}/quick-confirm', [DashboardController::class, 'quickConfirmOrder']);
            Route::get('warehouse-addresses', [UserController::class, 'getWarehouseAddresses']);

            Route::apiResource('promotions', PromotionController::class);
            Route::get('promotions/products/my', [PromotionController::class, 'getUserProducts']);

            Route::post('questions/{question}/answer', [ProductQuestionController::class, 'answer']);
            Route::put('questions/{question}/answer', [ProductQuestionController::class, 'updateAnswer']);
        });

        // Common Shop Features
        Route::post('shops/{shop}/subscribe', [FollowController::class, 'store']);
        Route::delete('shops/{shop}/subscribe', [FollowController::class, 'destroy']);
        Route::get('shops/{shop}/followers-count', [FollowController::class, 'count']);
        Route::get('users/{user}/followers-count', [FollowController::class, 'count']);

        // Orders & Returns (Common for client/seller)
        Route::middleware('role:client,seller')->group(static function (): void {
            Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'update', 'show']);
            Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
            Route::get('orders/{order}/qr', [OrderController::class, 'generateQR']);
            Route::post('orders/precalculate', [OrderController::class, 'precalculate']);

            Route::apiResource('favorite-posts', FavoritePostController::class)->only(['index', 'store', 'destroy']);
            Route::get('favorite-posts/check/{post}', [FavoritePostController::class, 'check']);

            Route::prefix('returns')->group(static function (): void {
                Route::get('/', [ReturnsController::class, 'index']);
                Route::post('/', [ReturnsController::class, 'store']);
                Route::get('order/{order}/eligible-items', [ReturnsController::class, 'getEligibleItems']);
                Route::post('qr/scan', [ReturnsController::class, 'scanQR'])->name('returns.scan');

                Route::prefix('{return}')->group(static function (): void {
                    Route::get('/', [ReturnsController::class, 'show']);
                    Route::get('qr', [ReturnsController::class, 'generateQR']);
                    Route::post('qr/regenerate', [ReturnsController::class, 'regenerateQR']);
                    Route::post('approve', [ReturnsController::class, 'approve']); // Одобрение заявки
                    Route::post('reject', [ReturnsController::class, 'reject']);   // Отклонение заявки
                    Route::post('in-transit', [ReturnsController::class, 'markInTransit']);
                    Route::post('received', [ReturnsController::class, 'markReceived']);    // Получено складом
                    Route::post('condition-ok', [ReturnsController::class, 'markConditionOk']);
                    Route::post('condition-bad', [ReturnsController::class, 'markConditionBad']);
                    Route::post('rejected-by-warehouse', [ReturnsController::class, 'markRejectedByWarehouse']);
                    Route::post('refund-initiated', [ReturnsController::class, 'markRefundInitiated']);
                    Route::post('completed', [ReturnsController::class, 'markCompleted']);
                    Route::post('in-transit-back', [ReturnsController::class, 'markInTransitBackToCustomer']);
                });
            });
        });

        // Messaging
        Route::apiResource('chats', ChatController::class)->only(['index', 'store', 'show']);
        Route::post('chats/group', [ChatController::class, 'createGroup']);
        Route::get('chats/{chat}/messages', [MessageController::class, 'index']);
        Route::post('chats/{chat}/messages', [MessageController::class, 'store']);

        // System Services
        Route::get('search', [SearchController::class, 'search']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('recommendations/hybrid-feed', [RecommendationController::class, 'getHybridFeed']);
        Route::get('recommendations/discovery-feed', [RecommendationController::class, 'getDiscoveryFeed']);

        Route::prefix('sessions')->group(function () {
            Route::get('/', [SessionController::class, 'index']);
            Route::delete('destroy-others', [SessionController::class, 'destroyOthers']);
            Route::delete('{id}', [SessionController::class, 'destroy']);
        });
    });

    // Public Utilities (No Auth Required)
    Route::get('currencies', [CurrencyController::class, 'index']);
    Route::get('currency-rates', [CurrencyController::class, 'rates']);
    Route::post('convert', [CurrencyController::class, 'convert']);
});
