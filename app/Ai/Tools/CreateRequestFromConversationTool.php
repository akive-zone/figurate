<?php

namespace App\Ai\Tools;

use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class CreateRequestFromConversationTool implements Tool
{
    public function __construct(
        protected Thread $thread,
        protected Channel $channel,
        protected User $actor,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Create a request from the current conversation once the user intent is clear. Prefer calling this instead of asking repeated intake questions.';
    }

    /**
     * Execute the tool.
     */
    public function handle(ToolRequest $request): Stringable|string
    {
        if (! $this->channel->hasActor($this->actor)) {
            return $this->encodeError('Only channel members can create a request.');
        }

        $existingRequest = $this->channel->primaryRequest();

        if ($existingRequest) {
            return json_encode([
                'ok' => true,
                'created' => false,
                'request_id' => $existingRequest->id,
                'request_ulid' => $existingRequest->ulid,
                'status' => $existingRequest->status,
                'message' => 'Request already exists for this channel.',
            ], JSON_UNESCAPED_SLASHES);
        }

        $title = trim((string) ($request['title'] ?? ''));
        $description = trim((string) ($request['description'] ?? ''));

        if ($title === '' && $description === '') {
            return $this->encodeError('Either title or description is required.');
        }

        $flowType = trim((string) ($request['flow_type'] ?? 'ubid'));
        if ($flowType === '') {
            $flowType = 'ubid';
        }

        $status = trim((string) ($request['status'] ?? 'open'));
        if ($status === '') {
            $status = 'open';
        }

        /** @var ServiceRequest $serviceRequest */
        $serviceRequest = DB::transaction(function () use ($title, $description, $flowType, $status): ServiceRequest {
            /** @var ServiceRequest $serviceRequest */
            $serviceRequest = ServiceRequest::query()->create([
                'type' => 'request.created',
                'status' => $status,
                'payload' => [
                    'flow_type' => $flowType,
                    'title' => $title !== '' ? $title : null,
                    'description' => $description !== '' ? $description : null,
                ],
                'meta' => [
                    'source' => 'tool.create_request_from_conversation',
                    'channel_uuid' => $this->channel->uuid,
                    'thread_uuid' => $this->thread->uuid,
                ],
                'occurred_at' => now(),
            ]);

            $this->channel->requests()->syncWithoutDetaching([$serviceRequest->id]);
            $this->attachAsker($serviceRequest);

            $this->thread->forceFill([
                'threadable_type' => $serviceRequest->getMorphClass(),
                'threadable_id' => $serviceRequest->getKey(),
                'phase' => 'request_open',
                'status' => 'open',
            ])->save();

            $this->thread->messages()->create([
                'senderable_type' => null,
                'senderable_id' => null,
                'type' => 'system',
                'tag' => 'request_created',
                'body' => "Request #{$serviceRequest->id} has been created for this conversation.",
                'attachments' => null,
                'meta' => [
                    'source' => 'tool',
                    'tool' => self::class,
                    'request_id' => $serviceRequest->id,
                    'request_ulid' => $serviceRequest->ulid,
                ],
            ]);

            return $serviceRequest;
        });

        return json_encode([
            'ok' => true,
            'created' => true,
            'request_id' => $serviceRequest->id,
            'request_ulid' => $serviceRequest->ulid,
            'status' => $serviceRequest->status,
            'flow_type' => $serviceRequest->flow_type,
            'thread_id' => $this->thread->id,
            'thread_uuid' => $this->thread->uuid,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string(),
            'description' => $schema->string(),
            'flow_type' => $schema->string(),
            'status' => $schema->string(),
        ];
    }

    protected function attachAsker(ServiceRequest $serviceRequest): void
    {
        try {
            $serviceRequest->users()->syncWithoutDetaching([
                $this->actor->getKey() => [
                    'action' => ServiceRequest::ActionAsker,
                    'status' => 'active',
                ],
            ]);
        } catch (QueryException) {
            // request_actors may not exist in early schema states.
        }
    }

    protected function encodeError(string $message): string
    {
        return json_encode([
            'ok' => false,
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES);
    }
}
