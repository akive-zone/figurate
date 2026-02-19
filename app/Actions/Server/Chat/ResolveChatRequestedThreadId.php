<?php

namespace App\Actions\Server\Chat;

use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;

class ResolveChatRequestedThreadId
{
    public function __invoke(mixed $threadUuid, Channel $channel, ?ServiceRequest $serviceRequest): ?int
    {
        if (! is_string($threadUuid) || $threadUuid === '') {
            return null;
        }

        $query = Thread::query()
            ->where('uuid', $threadUuid)
            ->where(function ($relationQuery) use ($channel, $serviceRequest): void {
                if ($serviceRequest) {
                    $relationQuery->orWhere(function ($requestQuery) use ($serviceRequest): void {
                        $requestQuery
                            ->where('threadable_type', $serviceRequest->getMorphClass())
                            ->where('threadable_id', $serviceRequest->getKey());
                    });
                }

                $relationQuery->orWhere(function ($channelQuery) use ($channel): void {
                    $channelQuery
                        ->where('threadable_type', $channel->getMorphClass())
                        ->where('threadable_id', $channel->getKey());
                });
            });

        $thread = $query->first();

        if (! $thread) {
            abort(404, 'The selected thread does not belong to this channel.');
        }

        return $thread->id;
    }
}
