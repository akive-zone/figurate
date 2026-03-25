<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;

class EnsureThreadMembership
{
    public function execute(Thread $thread, User $actor): void
    {
        $thread->actors()->firstOrCreate(
            [
                'actorable_type' => $actor->getMorphClass(),
                'actorable_id' => $actor->getKey(),
                'role' => ThreadActor::RoleMember,
            ],
            [
                'status' => ThreadActor::StatusActive,
                'priority' => 99,
                'config' => null,
            ],
        );
    }
}
