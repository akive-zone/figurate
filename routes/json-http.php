<?php

use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\CredentialController;
use App\Http\Controllers\Api\FormController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', RegisterController::class)->name('api.auth.register');
    Route::post('login', LoginController::class)->name('api.auth.login');
    Route::post('logout', LogoutController::class)
        ->middleware(['auth:sanctum,passport']);
    Route::post('broadcasting', [BroadcastController::class, 'authenticate'])
        ->middleware(['auth:sanctum,passport']);

    Route::get('user', [CurrentUserController::class, 'show'])
        ->middleware(['auth:sanctum,passport'])
        ->name('api.auth.user.show');
    Route::patch('user', [CurrentUserController::class, 'update'])
        ->middleware(['auth:sanctum,passport'])
        ->name('api.auth.user.update');

    Route::prefix('passkeys')
        ->name('api.passkeys.')
        ->group(function (): void {
            Route::get('/', [PasskeyController::class, 'index'])
                ->middleware(['auth:sanctum,passport'])
                ->name('index');
            Route::post('/options', [PasskeyController::class, 'generateRegisterOptions'])
                ->name('register-options');
            Route::post('/', [PasskeyController::class, 'store'])
                ->name('store');
            Route::delete('/{passkey}', [PasskeyController::class, 'destroy'])
                ->middleware(['auth:sanctum,passport'])
                ->name('destroy');
        });

    Route::prefix('credentials')
        ->middleware(['auth:sanctum,passport'])
        ->group(function (): void {
            Route::get('/', [CredentialController::class, 'index'])->name('api.auth.credentials.index');
            Route::post('/', [CredentialController::class, 'store'])->name('api.auth.credentials.store');
            Route::delete('/{credential}', [CredentialController::class, 'destroy'])->name('api.auth.credentials.destroy');
        });
});

Route::prefix('form')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [FormController::class, 'store'])
        ->middleware(['api.ability:forms:submit', 'api.idempotent'])
        ->name('api.form.store');

});
