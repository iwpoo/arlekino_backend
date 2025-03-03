<?php

use App\Http\Controllers\API\v1\AuthController;
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

    Route::apiResource('posts', PostController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->middleware('auth:sanctum');
});
