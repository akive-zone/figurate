<?php

namespace Figurate\FulfillmentManager\Listeners;

use App\Events\Server\Ai\ConversationPostRequested;
use Figurate\FulfillmentManager\Ai\Support\FulfillmentContext;
use Figurate\FulfillmentManager\Models\Order;
use Illuminate\Support\Facades\DB;

class HandleConversationPostRequested
{
    public function __construct(
        protected FulfillmentContext $fulfillmentContext = new FulfillmentContext,
    ) {}

    public function handle(ConversationPostRequested $event): void
    {
        if ($event->handled()) {
            return;
        }

        if ($event->isSubjectIntent()) {
            $event->respond($this->createSubjectPost($event));

            return;
        }

        if ($event->isExecutionIntent()) {
            $event->respond($this->createExecutionPost($event));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function createSubjectPost(ConversationPostRequested $event): array
    {
        $existingSubject = $event->channel->primaryRequestPost();

        if ($existingSubject) {
            return [
                'ok' => true,
                'created' => false,
                'intent' => 'subject',
                'post_id' => $existingSubject->id,
                'post_ulid' => $existingSubject->ulid,
                'post_type' => $existingSubject->type,
                'status' => $existingSubject->status,
                'message' => 'Primary subject already exists for this conversation.',
            ];
        }

        if ($event->title === null && $event->description === null) {
            return [
                'ok' => false,
                'error' => 'Either title or description is required.',
            ];
        }

        $flowType = $event->flowType ?? 'ubid';
        $status = $event->status ?? 'open';

        $subjectPost = DB::transaction(function () use ($event, $flowType, $status) {
            $subjectPost = $this->fulfillmentContext->createFulfillmentSubject([
                'type' => 'request.created',
                'status' => $status,
                'payload' => [
                    'flow_type' => $flowType,
                    'title' => $event->title,
                    'description' => $event->description,
                ],
                'meta' => [
                    'source' => 'tool.create_post_from_conversation',
                    'intent' => 'subject',
                    'channel_uuid' => $event->channel->uuid,
                    'thread_uuid' => $event->thread->uuid,
                ],
                'occurred_at' => now(),
            ]);

            $event->channel->relations()->create([
                'relationable_type' => $subjectPost->getMorphClass(),
                'relationable_id' => $subjectPost->getKey(),
                'type' => 'request',
                'purpose' => 'primary',
            ]);

            $subjectPost->attachRelation($event->channel, 'channel');
            $this->fulfillmentContext->attachAsker($subjectPost, $event->actor);

            $event->thread->forceFill([
                'threadable_type' => $subjectPost->getMorphClass(),
                'threadable_id' => $subjectPost->getKey(),
                'phase' => 'request_open',
                'status' => 'open',
            ])->save();

            $event->thread->messages()->create([
                'senderable_type' => null,
                'senderable_id' => null,
                'type' => 'system',
                'tag' => 'request_created',
                'text' => "Request #{$subjectPost->id} has been created for this conversation.",
                'attachments' => null,
                'meta' => [
                    'source' => 'tool',
                    'tool' => 'conversation_post_requested',
                    'request_id' => $subjectPost->id,
                    'request_ulid' => $subjectPost->ulid,
                ],
            ]);

            return $subjectPost;
        });

        return [
            'ok' => true,
            'created' => true,
            'intent' => 'subject',
            'post_id' => $subjectPost->id,
            'post_ulid' => $subjectPost->ulid,
            'post_type' => $subjectPost->type,
            'status' => $subjectPost->status,
            'flow_type' => $this->fulfillmentContext->flowType($subjectPost),
            'thread_id' => $event->thread->id,
            'thread_uuid' => $event->thread->uuid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function createExecutionPost(ConversationPostRequested $event): array
    {
        $subjectPost = $this->fulfillmentContext->resolveSubjectFromThread($event->thread);

        if ($subjectPost === null) {
            return [
                'ok' => false,
                'error' => 'No conversation subject exists yet. Create a subject post first.',
            ];
        }

        $status = $event->status ?? 'open';

        $orderPost = DB::transaction(function () use ($event, $status, $subjectPost) {
            $orderPost = Order::query()->create([
                'type' => 'order.created',
                'status' => $status,
                'payload' => [
                    'title' => $event->title,
                    'description' => $event->description,
                ],
                'meta' => [
                    'source' => 'tool.create_post_from_conversation',
                    'intent' => 'execution',
                    'channel_uuid' => $event->channel->uuid,
                    'thread_uuid' => $event->thread->uuid,
                ],
                'occurred_at' => now(),
            ]);

            $orderPost->attachRelation($event->channel, 'channel');
            $orderPost->attachRelation($event->thread, 'primary');
            $orderPost->attachRelation($subjectPost, 'request');

            $event->thread->forceFill([
                'phase' => 'order_active',
                'status' => 'open',
            ])->save();

            $event->thread->messages()->create([
                'senderable_type' => null,
                'senderable_id' => null,
                'type' => 'system',
                'tag' => 'order_post_created',
                'text' => "Order post #{$orderPost->id} has been created for this conversation.",
                'attachments' => null,
                'meta' => [
                    'source' => 'tool',
                    'tool' => 'conversation_post_requested',
                    'order_post_id' => $orderPost->id,
                ],
            ]);

            return $orderPost;
        });

        return [
            'ok' => true,
            'created' => true,
            'intent' => 'execution',
            'post_id' => $orderPost->id,
            'post_ulid' => $orderPost->ulid,
            'post_type' => $orderPost->type,
            'status' => $orderPost->status,
            'thread_id' => $event->thread->id,
            'thread_uuid' => $event->thread->uuid,
        ];
    }
}
