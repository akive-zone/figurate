<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'native.launcher')->name('launcher');

Route::prefix('signal')
    ->name('signal.')
    ->group(function () {
        Route::view('/', 'signal.index')->name('index');
    });
