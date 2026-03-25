<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use Illuminate\Support\Collection;

class ResolveActiveThreadPresenters
{
    /**
     * @return Collection<int, ThreadActor>
     */
    public function execute(Thread $thread): Collection
    {
        return $thread->actors()
            ->where('role', ThreadActor::RolePresenter)
            ->where('status', ThreadActor::StatusActive)
            ->orderBy('priority')
            ->get();
    }
}
