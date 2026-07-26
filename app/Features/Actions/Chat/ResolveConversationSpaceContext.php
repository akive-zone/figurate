<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Space;
use App\Models\Server\User;

class ResolveConversationSpaceContext
{
    public function __construct(
        public BootstrapConversationSpaceContext $bootstrapConversationSpaceContext,
    ) {}

    public function execute(mixed $spaceUuid, User $actor): Space
    {
        if (is_string($spaceUuid) && $spaceUuid !== '') {
            return Space::query()->where('uuid', $spaceUuid)->firstOrFail();
        }

        return $this->bootstrapConversationSpaceContext->execute($actor);
    }
}
