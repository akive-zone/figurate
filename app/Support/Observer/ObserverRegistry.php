<?php

namespace App\Support\Observer;

use App\Ai\Tools\SafetyGuardObserver;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use Laravel\Ai\Contracts\Tool;

class ObserverRegistry
{
    public function resolve(
        ThreadActor $threadActor,
        Thread $thread,
        Message $message,
    ): ?Tool {
        return match ($threadActor->actorName()) {
            ThreadActor::ActorSafetyGuard => new SafetyGuardObserver($thread, $message, $threadActor),
            default => null,
        };
    }
}
