<?php

use App\Http\Controllers\API\v1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(static function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware(['auth:sanctum'])->group(static function (): void {
        Route::get('check-token', [AuthController::class, 'checkToken']);
    });
});
