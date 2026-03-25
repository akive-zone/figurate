<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use Illuminate\Support\Collection;

class FindAssistantRepliesForMessage
{
    /**
     * @param  Collection<int, ThreadActor>  $activePresenters
     * @return Collection<int, Message>
     */
    public function execute(Thread $thread, Message $userMessage, Collection $activePresenters): Collection
    {
        if ($activePresenters->isEmpty()) {
            return collect();
        }

        $presenterKeys = $activePresenters
            ->map(fn (ThreadActor $presenter): string => $presenter->actorName())
            ->filter(fn (?string $actorKey): bool => is_string($actorKey) && $actorKey !== '')
            ->values();

        if ($presenterKeys->isEmpty()) {
            return collect();
        }

        return Message::query()
            ->where('messageable_type', $thread->getMorphClass())
            ->where('messageable_id', $thread->getKey())
            ->whereNull('senderable_type')
            ->whereNull('senderable_id')
            ->where('meta->source', 'agent_response')
            ->where('meta->in_reply_to_message_id', $userMessage->id)
            ->whereIn('meta->actor_key', $presenterKeys->all())
            ->orderBy('id')
            ->get();
    }
}
