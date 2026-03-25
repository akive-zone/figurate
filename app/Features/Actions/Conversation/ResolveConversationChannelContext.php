<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Channel;
use App\Models\Server\User;

class ResolveConversationChannelContext
{
    public function __construct(
        public BootstrapConversationChannelContext $bootstrapConversationChannelContext,
    ) {}

    public function execute(mixed $channelUuid, User $actor): Channel
    {
        if (is_string($channelUuid) && $channelUuid !== '') {
            return Channel::query()->where('uuid', $channelUuid)->firstOrFail();
        }

        return $this->bootstrapConversationChannelContext->execute($actor);
    }
}
