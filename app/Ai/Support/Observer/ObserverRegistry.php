<?php

namespace App\Ai\Support\Observer;

use App\Ai\Support\Observer\Contracts\ObserverSkill;
use App\Ai\Tools\SafetyGuardObserver;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;

class ObserverRegistry
{
    public function __construct(
        protected ObserverSkillRepository $observerSkillRepository,
    ) {}

    public function resolve(
        ThreadActor $threadActor,
        Thread $thread,
        Message $message,
    ): ?ObserverSkill {
        $skill = $this->observerSkillRepository->resolve($threadActor);

        if (! is_array($skill)) {
            return null;
        }

        return match (($skill['observer'] ?? null)) {
            'safety_guard' => new SafetyGuardObserver(
                thread: $thread,
                message: $message,
                threadActor: $threadActor,
                skill: $skill,
            ),
            default => null,
        };
    }
}
