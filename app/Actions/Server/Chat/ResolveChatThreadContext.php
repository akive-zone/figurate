<?php

namespace App\Actions\Server\Chat;

use App\Models\Server\Channel;
use App\Models\Server\Thread;

class ResolveChatThreadContext
{
    public function __invoke(string $threadUuid, mixed $channelUuid = null): array
    {
        $thread = Thread::query()
            ->where('uuid', $threadUuid)
            ->firstOrFail();

        return [$this->resolveThreadChannel($thread, $channelUuid), $thread->id];
    }

    protected function resolveThreadChannel(Thread $thread, mixed $channelUuid): Channel
    {
        if (is_string($channelUuid) && $channelUuid !== '') {
            $channel = Channel::query()
                ->where('uuid', $channelUuid)
                ->firstOrFail();

            if (! $channel->conversationThreadIds()->contains($thread->getKey())) {
                abort(404, 'The selected thread does not belong to this channel.');
            }

            return $channel;
        }

        $threadable = $thread->threadable;

        if ($threadable instanceof Channel) {
            return $threadable;
        }

        abort(422, 'A channel id is required for this thread context.');
    }
}
