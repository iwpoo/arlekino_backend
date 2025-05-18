<?php

use App\Http\Controllers\API\v1\AuthController;
use App\Http\Controllers\API\v1\Post\CommentController;
use App\Http\Controllers\API\v1\Post\LikeController;
use App\Http\Controllers\API\v1\Post\PostController;
use App\Http\Controllers\API\v1\Product\CategoryController;
use App\Http\Controllers\API\v1\Product\ProductController;
use App\Http\Controllers\API\v1\SearchController;
use App\Http\Controllers\API\v1\StoryController;
use App\Http\Controllers\API\v1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(static function (): void {
    Route::prefix('auth')->group(static function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

        Route::middleware(['auth:sanctum'])->group(static function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('/email/verification-notification', [AuthController::class, 'sendEmailVerification']);
        });
    });

    Route::apiResource('posts', PostController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->middleware(['auth:sanctum']);
    Route::post('posts/{post}/view', [PostController::class, 'incrementViews'])->middleware(['auth:sanctum']);
    Route::post('posts/{post}/like', [LikeController::class, 'like'])->middleware(['auth:sanctum']);
    Route::delete('posts/{post}/unlike', [LikeController::class, 'unlike'])->middleware(['auth:sanctum']);
    Route::apiResource('posts.comments', CommentController::class)->shallow()->middleware(['auth:sanctum']);

    Route::apiResource('products', ProductController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->middleware(['auth:sanctum']);
    Route::get('categories', [CategoryController::class, 'getCategoriesWithQuestions'])->middleware(['auth:sanctum']);
    Route::get('categories/{categoryId}/questions', [CategoryController::class, 'getQuestionsByCategory'])->middleware(['auth:sanctum']);

    Route::apiResource('stories', StoryController::class)->only(['index', 'store', 'show', 'destroy'])->middleware(['auth:sanctum']);
    Route::post('stories/{story}/view', [StoryController::class, 'markAsViewed'])->middleware(['auth:sanctum']);

    Route::get('users/with-story', [UserController::class, 'getUsersWithStories'])->middleware(['auth:sanctum']);
    Route::get('users/{user}/stories', [UserController::class, 'userStories'])->middleware(['auth:sanctum']);

    Route::get('/search', [SearchController::class, 'search'])->middleware(['auth:sanctum']);
});
