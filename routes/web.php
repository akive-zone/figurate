<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (config('app.context') === 'native') {
        return view('native.launcher');
    }

    return redirect()->to('/signal');
})->name('launcher');

Route::prefix('signal')
    ->name('signal.')
    ->group(function () {
        Route::view('/', 'signal.index')->name('index');
    });

Route::fallback(function () {
    return redirect()->to('/signal');
});
