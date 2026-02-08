<?php

namespace App\Support\ThreadObservers;

use App\Models\Server\ThreadActor;
use App\Support\ThreadObservers\Contracts\ThreadObserverContract;

class ThreadActorObserverRegistry
{
    public function resolve(ThreadActor $threadActor): ?ThreadObserverContract
    {
        return match ($threadActor->actorName()) {
            ThreadActor::ActorSafetyGuard => app(SafetyGuardObserver::class),
            default => null,
        };
    }
}
