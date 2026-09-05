<?php

namespace App\Features\Operations\Chat;

use App\Features\Actions\Chat\PersistActiveConversationThread;
use App\Features\Actions\Chat\ResolveBaseConversationThread;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;

class ResolveConversationThreadOperation
{
    public function __construct(
        protected ResolveBaseConversationThread $resolveBaseConversationThread,
        protected PersistActiveConversationThread $persistActiveConversationThread,
    ) {}

    public function run(
        Space $space,
        User $actor,
        ?int $thread = null,
    ): Thread {
        return DB::transaction(function () use ($space, $actor, $thread): Thread {
            $resolvedThread = $this->resolveBaseConversationThread->execute($space, $actor, $thread);

            $this->persistActiveConversationThread->execute($space, $actor, $resolvedThread);

            return $resolvedThread;
        });
    }
}
