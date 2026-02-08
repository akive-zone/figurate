<?php

namespace App\Support\ThreadObservers;

use App\Models\Server\ThreadObserver;
use App\Support\ThreadObservers\Contracts\ThreadObserverContract;

class ThreadObserverRegistry
{
    public function resolve(string $observerKey): ?ThreadObserverContract
    {
        return match ($observerKey) {
            ThreadObserver::SafetyGuard => app(SafetyGuardObserver::class),
            default => null,
        };
    }
}
