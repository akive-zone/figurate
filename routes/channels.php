<?php

use App\Models\Server\Space;
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
}, ['guards' => ['web', 'sanctum', 'passport']]);

Broadcast::channel('spaces.{spaceUuid}', function (User $user, string $spaceUuid): bool {
    $space = Space::query()
        ->where('uuid', $spaceUuid)
        ->first();

    if (! $space) {
        return false;
    }

    return $user->can('view', $space);
}, ['guards' => ['web', 'sanctum', 'passport']]);

Broadcast::channel('users.{userUuid}.notifications', function (User $user, string $userUuid): bool {
    return is_string($user->uuid)
        && $user->uuid !== ''
        && hash_equals($user->uuid, $userUuid);
}, ['guards' => ['web', 'sanctum', 'passport']]);
