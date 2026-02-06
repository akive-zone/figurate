<?php

use App\Http\Controllers\Server\Auth\SocialiteController;
use App\Http\Controllers\Signal\ConversationController;
use App\Http\Controllers\Signal\ConversationFulfillmentController;
use App\Http\Controllers\Signal\ConversationMessageController;
use App\Http\Controllers\Signal\RequestConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->name('auth.')
    ->group(function () {
        Route::get('{provider}/redirect', [SocialiteController::class, 'redirect'])
            ->name('redirect');
        Route::get('{provider}/callback', [SocialiteController::class, 'callback'])
            ->name('callback');
    });

Route::get('/', function () {
    if (config('app.context') === 'native') {
        return view('native.launcher');
    }

    return redirect()->to('/signal');
})->name('launcher');

Route::prefix('signal')
    ->name('signal.')
    ->group(function () {
        Route::get('/', [ConversationController::class, 'index'])->name('index');
        Route::get('/requests/new', [RequestConversationController::class, 'create'])->name('requests.create');
        Route::post('/requests', [RequestConversationController::class, 'store'])->name('requests.store');
        Route::get('/chat/{conversation}', [ConversationController::class, 'show'])->name('chat.show');
        Route::post('/chat/{conversation}/messages', [ConversationMessageController::class, 'store'])->name('chat.messages.store');
        Route::post('/chat/{conversation}/quotes/{quote}/accept', [ConversationFulfillmentController::class, 'acceptQuote'])->name('chat.quotes.accept');
    });

Route::fallback(function () {
    return redirect()->to('/signal');
});
