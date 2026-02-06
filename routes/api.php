<?php

use App\Http\Controllers\Server\Auth\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('studio/auth')->group(function (): void {
    Route::post('register', [ApiTokenController::class, 'register']);
    Route::post('login', [ApiTokenController::class, 'login']);
    Route::post('logout', [ApiTokenController::class, 'logout'])
        ->middleware(['auth:sanctum']);
});
