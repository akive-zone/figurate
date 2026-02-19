<?php

use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('threads.{threadUuid}', function (User $user, string $threadUuid): bool {
    $thread = Thread::query()
        ->where('uuid', $threadUuid)
        ->first();

    if (! $thread) {
        return false;
    }

    return $user->can('view', $thread);
});
