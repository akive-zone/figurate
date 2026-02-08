<?php

namespace App\Support\ThreadObservers;

use App\Models\Server\ThreadActor;
use App\Support\ThreadObservers\Contracts\ThreadObserverContract;

class ThreadActorObserverRegistry
{
    public function resolve(string $actorKey): ?ThreadObserverContract
    {
        return match ($actorKey) {
            ThreadActor::ActorSafetyGuard => app(SafetyGuardObserver::class),
            default => null,
        };
    }
}
