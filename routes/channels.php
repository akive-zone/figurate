<?php

use App\Models\Server\Channel;
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

Broadcast::channel('channels.{channelUuid}', function (User $user, string $channelUuid): bool {
    $channel = Channel::query()
        ->where('uuid', $channelUuid)
        ->first();

    if (! $channel) {
        return false;
    }

    return $user->can('view', $channel);
}, ['guards' => ['web', 'sanctum', 'passport']]);

Broadcast::channel('users.{userUuid}.notifications', function (User $user, string $userUuid): bool {
    return is_string($user->uuid)
        && $user->uuid !== ''
        && hash_equals($user->uuid, $userUuid);
}, ['guards' => ['web', 'sanctum', 'passport']]);
