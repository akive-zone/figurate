<?php

namespace App\Actions\Server\Chat;

use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\User;

class ResolveChatChannelContext
{
    public function __construct(
        public BootstrapChatChannelContext $bootstrapChatChannelContext,
    ) {}

    /**
     * @return array{0: Channel, 1: ServiceRequest|null}
     */
    public function __invoke(mixed $channelUuid, User $actor): array
    {
        if (is_string($channelUuid) && $channelUuid !== '') {
            $channel = Channel::query()->where('uuid', $channelUuid)->firstOrFail();
            $serviceRequest = $channel->requests()->first();

            return [$channel, $serviceRequest];
        }

        return ($this->bootstrapChatChannelContext)($actor);
    }
}
