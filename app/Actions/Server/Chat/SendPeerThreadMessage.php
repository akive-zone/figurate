<?php

namespace App\Actions\Server\Chat;

use App\Jobs\ProcessThreadObservers;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Collection;

class SendPeerThreadMessage
{
    /**
     * @param  Collection<int, array{path: string, original_name: string}>  $attachments
     */
    public function __invoke(
        Channel $channel,
        ?ServiceRequest $serviceRequest,
        Thread $thread,
        User $actor,
        ?string $body,
        Collection $attachments,
    ): Message {
        if (! $this->canActorWrite($channel, $serviceRequest, $actor)) {
            abort(403);
        }

        $message = $thread->messages()->create([
            'senderable_type' => $actor->getMorphClass(),
            'senderable_id' => $actor->getKey(),
            'type' => 'text',
            'body' => $body,
            'attachments' => null,
            'meta' => [
                'source' => 'peer_message',
            ],
        ]);

        $attachments->each(function (array $attachment) use ($message): void {
            $message->addMedia($attachment['path'])
                ->usingName(pathinfo($attachment['original_name'], PATHINFO_FILENAME))
                ->usingFileName($attachment['original_name'])
                ->toMediaCollection('attachments');
        });

        if ($attachments->isNotEmpty()) {
            $message->syncAttachmentPayload();
        }

        ProcessThreadObservers::dispatch($thread->id, $message->id);

        return $message;
    }

    protected function canActorWrite(Channel $channel, ?ServiceRequest $serviceRequest, User $actor): bool
    {
        if ($serviceRequest) {
            return $serviceRequest->hasParticipant($actor);
        }

        return $channel->hasActor($actor);
    }
}
