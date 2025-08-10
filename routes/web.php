<?php

use App\Http\Controllers\API\v1\AuthController;
use App\Http\Controllers\API\v1\BankCardController;
use App\Http\Controllers\API\v1\CartController;
use App\Http\Controllers\API\v1\FavoriteProductController;
use App\Http\Controllers\API\v1\OrderController;
use App\Http\Controllers\API\v1\Post\CommentController;
use App\Http\Controllers\API\v1\Post\LikeController;
use App\Http\Controllers\API\v1\Post\PostController;
use App\Http\Controllers\API\v1\Product\CategoryController;
use App\Http\Controllers\API\v1\Product\ProductController;
use App\Http\Controllers\API\v1\PurchaseController;
use App\Http\Controllers\API\v1\SearchController;
use App\Http\Controllers\API\v1\SessionController;
use App\Http\Controllers\API\v1\StoryController;
use App\Http\Controllers\API\v1\UserAddressController;
use App\Http\Controllers\API\v1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(static function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

    Route::middleware(['auth'])->group(static function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('/email/verification-notification', [AuthController::class, 'sendEmailVerification']);
    });
});

Route::resource('posts', PostController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->middleware(['auth']);
Route::post('posts/{post}/view', [PostController::class, 'incrementViews'])->middleware(['auth', 'role:client,seller']);
Route::post('posts/{post}/like', [LikeController::class, 'like'])->middleware(['auth', 'role:client,seller']);
Route::delete('posts/{post}/unlike', [LikeController::class, 'unlike'])->middleware(['auth', 'role:client,seller']);
Route::resource('posts.comments', CommentController::class)->shallow()->middleware(['auth', 'role:client,seller']);

Route::resource('products', ProductController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->middleware(['auth']);
Route::get('categories', [CategoryController::class, 'getCategoriesWithQuestions'])->middleware(['auth']);
Route::get('categories/{categoryId}/questions', [CategoryController::class, 'getQuestionsByCategory'])->middleware(['auth']);

Route::resource('stories', StoryController::class)->only(['index', 'store', 'show', 'destroy'])->middleware(['auth']);
Route::post('stories/{story}/view', [StoryController::class, 'markAsViewed'])->middleware(['auth']);

Route::resource('users', UserController::class)->only(['show', 'update', 'destroy'])->middleware(['auth']);
Route::get('users/with-story', [UserController::class, 'getUsersWithStories'])->middleware(['auth']);
Route::get('users/{user}/stories', [UserController::class, 'userStories'])->middleware(['auth']);

Route::middleware('auth')->group(static function (): void {
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::delete('/sessions/{id}', [SessionController::class, 'destroy']);
});

Route::get('/search', [SearchController::class, 'search'])->middleware(['auth']);

Route::middleware(['auth', 'role:seller,client'])->group(static function (): void {
    Route::resource('orders', OrderController::class)->only(['index', 'show', 'store', 'update']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('order.status.update');
    Route::get('/orders/{order}/qr', [OrderController::class, 'generateQR'])->name('order.qr.generate');
    Route::post('/orders/precalculate', [OrderController::class, 'precalculate'])->name('order.precalculate');
});

Route::middleware(['auth', 'role:client'])->group(static function (): void {
    Route::get('/favorites', [FavoriteProductController::class, 'index']);
    Route::post('/favorites/{product}', [FavoriteProductController::class, 'store']);
    Route::delete('/favorites/{product}', [FavoriteProductController::class, 'destroy']);
    Route::get('/favorites/check/{product}', [FavoriteProductController::class, 'check']);
});

Route::middleware(['auth', 'role:client'])->prefix('cart')->group(static function (): void {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/', [CartController::class, 'store']);
    Route::put('/{cartItem}', [CartController::class, 'update']);
    Route::delete('/{cartItem}', [CartController::class, 'destroy']);
});

Route::middleware(['auth', 'role:client'])->group(static function (): void {
    Route::resource('bank-cards', BankCardController::class)->only(['index', 'store', 'destroy']);
    Route::post('bank-cards/{card}/set-default', [BankCardController::class, 'setDefault']);
});

Route::middleware(['auth', 'role:client'])->group(static function (): void {
    Route::resource('users.addresses', UserAddressController::class)->only([
        'index', 'store', 'update', 'destroy'
    ]);

    Route::patch('/users/{user}/addresses/{address}/set-default',
        [UserAddressController::class, 'setDefault']);
});

Route::middleware(['auth', 'role:client'])->group(static function (): void {
    Route::get('/purchases', [PurchaseController::class, 'index']);
});
