<?php

use Figurate\AccountManager\Http\Controllers\Api\CurrentAccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/accounts')->group(function (): void {
    Route::get('/current', [CurrentAccountController::class, 'show'])->name('api.accounts.current');
});
