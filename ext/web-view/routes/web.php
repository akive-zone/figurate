<?php

use Figurate\WebView\Http\Controllers\Web\SpaceController;
use Illuminate\Support\Facades\Route;

Route::name('chat.')
    ->group(function () {
        Route::get('/', [SpaceController::class, 'index'])->name('index');
        Route::get('/create', [SpaceController::class, 'index'])->name('create');
        Route::get('/c/{space}', [SpaceController::class, 'show'])->name('show');
        Route::get('/c/{space}/t/{thread}', [SpaceController::class, 'showThread'])->name('thread');
    });
