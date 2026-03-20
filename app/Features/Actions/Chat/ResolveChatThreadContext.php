<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadRelation;
use Illuminate\Database\Eloquent\Model;

class ResolveChatThreadContext
{
    public function execute(string $threadUuid, mixed $channelUuid = null): array
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

        $threadMorphClass = $thread->getMorphClass();
        $relatedChannelId = ChannelRelation::query()
            ->where('relationable_type', $threadMorphClass)
            ->where('relationable_id', $thread->getKey())
            ->value('channel_id');

        if (is_int($relatedChannelId) && $relatedChannelId > 0) {
            return Channel::query()->findOrFail($relatedChannelId);
        }

        $channelMorphClass = (new Channel)->getMorphClass();
        $threadRelationChannelId = ThreadRelation::query()
            ->where('thread_id', $thread->getKey())
            ->where('relationable_type', $channelMorphClass)
            ->value('relationable_id');

        if (is_int($threadRelationChannelId) && $threadRelationChannelId > 0) {
            return Channel::query()->findOrFail($threadRelationChannelId);
        }

        if ($threadable instanceof Model) {
            $threadableChannelId = ChannelRelation::query()
                ->where('relationable_type', $threadable->getMorphClass())
                ->where('relationable_id', $threadable->getKey())
                ->value('channel_id');

            if (is_int($threadableChannelId) && $threadableChannelId > 0) {
                return Channel::query()->findOrFail($threadableChannelId);
            }
        }

        abort(422, 'A channel id is required for this thread context.');
    }
}
