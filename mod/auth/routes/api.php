<?php

use App\Http\Middleware\EnsureTransportUser;
use Figurate\Auth\Http\Controllers\Api\RobotUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/users')
    ->middleware(['api', 'auth:sanctum,passport'])
    ->group(function (): void {
        Route::post('/', [RobotUserController::class, 'store'])
            ->middleware(EnsureTransportUser::class.':subject')
            ->name('api.users.store');
    });
