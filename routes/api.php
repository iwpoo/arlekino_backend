<?php

use App\Http\Controllers\API\v1\AuthController;
use App\Http\Controllers\API\v1\Post\CommentController;
use App\Http\Controllers\API\v1\Post\LikeController;
use App\Http\Controllers\API\v1\Post\PostController;
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
});
