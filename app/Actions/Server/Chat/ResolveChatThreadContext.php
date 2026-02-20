<?php

namespace App\Actions\Server\Chat;

use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;

class ResolveChatThreadContext
{
    /**
     * @return array{0: Channel, 1: ServiceRequest|null, 2: int}
     */
    public function __invoke(string $threadUuid, mixed $channelUuid = null): array
    {
        $thread = Thread::query()
            ->where('uuid', $threadUuid)
            ->firstOrFail();

        $threadable = $thread->threadable;

        if ($threadable instanceof Channel) {
            $channel = $threadable;
            $serviceRequest = null;
        } elseif ($threadable instanceof ServiceRequest) {
            $serviceRequest = $threadable;
            $channel = $this->resolveRequestChannel($serviceRequest, $channelUuid);
        } else {
            abort(404, 'The selected thread is not available.');
        }

        if (is_string($channelUuid) && $channelUuid !== '' && $channel->uuid !== $channelUuid) {
            abort(404, 'The selected thread does not belong to this channel.');
        }

        return [$channel, $serviceRequest, $thread->id];
    }

    protected function resolveRequestChannel(ServiceRequest $serviceRequest, mixed $channelUuid): Channel
    {
        $query = $serviceRequest->channels();

        if (is_string($channelUuid) && $channelUuid !== '') {
            $query->where('channels.uuid', $channelUuid);
        }

        $channel = $query->first();

        if (! $channel) {
            abort(404, 'The selected thread is not linked to an accessible channel.');
        }

        return $channel;
    }
}
