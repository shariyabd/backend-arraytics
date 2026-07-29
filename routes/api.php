<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Contact\ContactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
|
| All endpoints are namespaced under /api/v1. Authentication is the Golden
| Module reference: login is public and throttled; every other endpoint is
| protected by the Sanctum bearer-token guard.
|
*/

Route::prefix('v1')->group(function (): void {
    // Public
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.v1.login');

    // Protected (valid bearer token required)
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');

        Route::apiResource('contacts', ContactController::class)
            ->names('api.v1.contacts');
    });
});
