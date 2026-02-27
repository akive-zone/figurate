<?php

namespace App\Ai\Tools;

use App\Ai\Support\FulfillmentContext;
use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class CreatePostFromConversationTool implements Tool
{
    public function __construct(
        protected Thread $thread,
        protected Channel $channel,
        protected User $actor,
        protected FulfillmentContext $fulfillmentContext = new FulfillmentContext,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Create a fulfillment post from the current conversation. Use post_kind=request for intake and post_kind=order when moving into execution.';
    }

    /**
     * Execute the tool.
     */
    public function handle(ToolRequest $request): Stringable|string
    {
        if (! $this->channel->hasActor($this->actor)) {
            return $this->encodeError('Only channel members can create posts from conversation.');
        }

        $postKind = trim((string) ($request['post_kind'] ?? 'request'));
        if (! in_array($postKind, ['request', 'order'], true)) {
            return $this->encodeError('post_kind must be either request or order.');
        }

        return $postKind === 'order'
            ? $this->createOrderPost($request)
            : $this->createRequestPost($request);
    }

    protected function createRequestPost(ToolRequest $request): string
    {
        $existingRequest = $this->channel->primaryRequestPost();
        if ($existingRequest) {
            return json_encode([
                'ok' => true,
                'created' => false,
                'post_kind' => 'request',
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

        $requestPost = DB::transaction(function () use ($title, $description, $flowType, $status) {
            $requestPost = $this->fulfillmentContext->createFulfillmentSubject([
                'type' => 'request.created',
                'status' => $status,
                'payload' => [
                    'flow_type' => $flowType,
                    'title' => $title !== '' ? $title : null,
                    'description' => $description !== '' ? $description : null,
                ],
                'meta' => [
                    'source' => 'tool.create_post_from_conversation',
                    'post_kind' => 'request',
                    'channel_uuid' => $this->channel->uuid,
                    'thread_uuid' => $this->thread->uuid,
                ],
                'occurred_at' => now(),
            ]);

            $this->channel->relations()->create([
                'relationable_type' => $requestPost->getMorphClass(),
                'relationable_id' => $requestPost->getKey(),
                'type' => 'request',
                'purpose' => 'primary',
            ]);

            $requestPost->attachRelation($this->channel, 'channel');
            $this->fulfillmentContext->attachAsker($requestPost, $this->actor);

            $this->thread->forceFill([
                'threadable_type' => $requestPost->getMorphClass(),
                'threadable_id' => $requestPost->getKey(),
                'phase' => 'request_open',
                'status' => 'open',
            ])->save();

            $this->thread->messages()->create([
                'senderable_type' => null,
                'senderable_id' => null,
                'type' => 'system',
                'tag' => 'request_created',
                'body' => "Request #{$requestPost->id} has been created for this conversation.",
                'attachments' => null,
                'meta' => [
                    'source' => 'tool',
                    'tool' => self::class,
                    'request_id' => $requestPost->id,
                    'request_ulid' => $requestPost->ulid,
                ],
            ]);

            return $requestPost;
        });

        return json_encode([
            'ok' => true,
            'created' => true,
            'post_kind' => 'request',
            'request_id' => $requestPost->id,
            'request_ulid' => $requestPost->ulid,
            'status' => $requestPost->status,
            'flow_type' => $this->fulfillmentContext->flowType($requestPost),
            'thread_id' => $this->thread->id,
            'thread_uuid' => $this->thread->uuid,
        ], JSON_UNESCAPED_SLASHES);
    }

    protected function createOrderPost(ToolRequest $request): string
    {
        $subjectPost = $this->fulfillmentContext->resolveSubjectFromThread($this->thread);
        if (! $subjectPost instanceof Post) {
            return $this->encodeError('No request context exists yet. Create a request post first.');
        }

        $title = trim((string) ($request['title'] ?? ''));
        $description = trim((string) ($request['description'] ?? ''));
        $status = trim((string) ($request['status'] ?? 'open'));

        if ($status === '') {
            $status = 'open';
        }

        $orderPost = DB::transaction(function () use ($title, $description, $status, $subjectPost) {
            $orderPost = Post::query()->create([
                'type' => 'order.created',
                'status' => $status,
                'payload' => [
                    'title' => $title !== '' ? $title : null,
                    'description' => $description !== '' ? $description : null,
                ],
                'meta' => [
                    'source' => 'tool.create_post_from_conversation',
                    'post_kind' => 'order',
                    'channel_uuid' => $this->channel->uuid,
                    'thread_uuid' => $this->thread->uuid,
                ],
                'occurred_at' => now(),
            ]);

            $orderPost->attachRelation($this->channel, 'channel');
            $orderPost->attachRelation($this->thread, 'primary');
            $orderPost->attachRelation($subjectPost, 'request');

            $this->thread->forceFill([
                'phase' => 'order_active',
                'status' => 'open',
            ])->save();

            $this->thread->messages()->create([
                'senderable_type' => null,
                'senderable_id' => null,
                'type' => 'system',
                'tag' => 'order_post_created',
                'body' => "Order post #{$orderPost->id} has been created for this conversation.",
                'attachments' => null,
                'meta' => [
                    'source' => 'tool',
                    'tool' => self::class,
                    'order_post_id' => $orderPost->id,
                ],
            ]);

            return $orderPost;
        });

        return json_encode([
            'ok' => true,
            'created' => true,
            'post_kind' => 'order',
            'post_id' => $orderPost->id,
            'post_ulid' => $orderPost->ulid,
            'type' => $orderPost->type,
            'status' => $orderPost->status,
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
            'post_kind' => $schema->string(),
            'title' => $schema->string(),
            'description' => $schema->string(),
            'flow_type' => $schema->string(),
            'status' => $schema->string(),
        ];
    }

    protected function encodeError(string $message): string
    {
        return json_encode([
            'ok' => false,
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES);
    }
}
