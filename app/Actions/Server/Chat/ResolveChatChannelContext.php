<?php

namespace App\Actions\Server\Chat;

use App\Models\Server\Channel;
use App\Models\Server\User;

class ResolveChatChannelContext
{
    public function __construct(
        public BootstrapChatChannelContext $bootstrapChatChannelContext,
    ) {}

    public function __invoke(mixed $channelUuid, User $actor): Channel
    {
        if (is_string($channelUuid) && $channelUuid !== '') {
            return Channel::query()->where('uuid', $channelUuid)->firstOrFail();
        }

        return ($this->bootstrapChatChannelContext)($actor);
    }
}
