<?php

namespace App\Features\Operations\Chat;

use App\Features\Actions\Chat\ApplyReceivedMessageA2uiMetadata;
use App\Features\Actions\Chat\CacheIdempotentConversationMessage;
use App\Features\Actions\Chat\FindAssistantRepliesForMessage;
use App\Features\Actions\Chat\FindExistingIdempotentConversationMessage;
use App\Features\Actions\Chat\NormalizeInboundConversationPayload;
use App\Features\Actions\Chat\QueuePresenterReplies;
use App\Features\Actions\Chat\ResolveActiveThreadPresenters;
use App\Features\Actions\Chat\ResolveConversationAttachments;
use App\Features\Actions\Chat\ResolveConversationIdempotencyKey;
use App\Features\Actions\Chat\ResolveConversationSpaceContext;
use App\Features\Actions\Chat\ResolveConversationThreadContext;
use App\Features\Actions\Chat\SendPeerThreadMessage;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use App\Support\Orchestrate\ResolveObserverDispatchPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class SubmitChatMessageOperation
{
    public function __construct(
        protected ResolveConversationThreadOperation $resolveConversationThreadOperation,
        protected ResolveConversationSpaceContext $resolveConversationSpaceContext,
        protected ResolveConversationThreadContext $resolveConversationThreadContext,
        protected NormalizeInboundConversationPayload $normalizeInboundConversationPayload,
        protected SendPeerThreadMessage $sendPeerThreadMessage,
        protected ApplyReceivedMessageA2uiMetadata $applyReceivedMessageA2uiMetadata,
        protected ResolveActiveThreadPresenters $resolveActiveThreadPresenters,
        protected ResolveConversationAttachments $resolveConversationAttachments,
        protected ResolveConversationIdempotencyKey $resolveConversationIdempotencyKey,
        protected FindExistingIdempotentConversationMessage $findExistingIdempotentConversationMessage,
        protected FindAssistantRepliesForMessage $findAssistantRepliesForMessage,
        protected CacheIdempotentConversationMessage $cacheIdempotentConversationMessage,
        protected QueuePresenterReplies $queuePresenterReplies,
        protected ResolveObserverDispatchPolicy $resolveObserverDispatchPolicy,
    ) {}

    /**
     * @param  array{
     *     actor: User,
     *     space?: mixed,
     *     thread?: mixed,
     *     content?: mixed,
     *     extra?: mixed,
     *     attachments?: array<int, mixed>,
     *     idempotency_key?: mixed
     * }  $input
     * @return array{status: int, body: array<string, mixed>}
     */
    public function run(array $input): array
    {
        $actor = $input['actor'];
        $spaceUuid = $input['space'] ?? null;
        $threadUuid = $input['thread'] ?? null;
        $contentPayload = $input['content'] ?? [];
        $extraPayload = $input['extra'] ?? [];
        $extraPayload = is_array($extraPayload) ? $extraPayload : [];
        $normalizedPayload = $this->normalizeInboundConversationPayload->execute(
            is_array($contentPayload) ? $contentPayload : [],
            $extraPayload,
        );
        $a2uiActions = $normalizedPayload['actions'];
        $a2uiErrors = $normalizedPayload['errors'];
        $a2uiClientDataModel = $normalizedPayload['client_data_model'];
        $a2uiClientCapabilities = $normalizedPayload['client_capabilities'];
        $thread = null;

        if (is_string($threadUuid) && $threadUuid !== '') {
            [$space, $thread] = $this->resolveConversationThreadContext->execute($threadUuid, $spaceUuid);
        } else {
            $space = $this->resolveConversationSpaceContext->execute($spaceUuid, $actor);
        }

        Gate::authorize('view', $space);
        Gate::authorize('create', Post::class);

        $normalizedRequestContent = $normalizedPayload['text'];

        $decision = $this->resolveConversationThreadOperation->run(
            space: $space,
            actor: $actor,
            thread: $thread,
        );
        $thread = $decision->thread;

        $observerPolicy = $this->resolveObserverDispatchPolicy->forThread($thread);
        $activePresenters = $this->resolveActiveThreadPresenters->execute($thread);
        $attachmentFiles = $this->resolveConversationAttachments->execute(
            is_array($input['attachments'] ?? null) ? $input['attachments'] : [],
        );

        $broadcastSpaceId = $this->broadcastSpaceIdForThread($thread);
        $content = $normalizedRequestContent;

        if ($content === null) {
            abort(422, 'A text message is required for chat submission.');
        }

        $idempotencyKey = $this->resolveConversationIdempotencyKey->execute($input['idempotency_key'] ?? null);
        $existingUserMessage = $this->findExistingIdempotentConversationMessage->execute($thread, $actor, $idempotencyKey);

        if ($existingUserMessage) {
            if ($existingUserMessage->text !== $content) {
                $existingUserMessage->forceFill([
                    'text' => $content,
                ])->save();
            }

            $existingAssistantMessages = $this->findAssistantRepliesForMessage->execute($thread, $existingUserMessage, $activePresenters);
            $firstAssistantMessage = $existingAssistantMessages->first();
            $expectedPresenterReplyCount = $this->expectedPresenterReplyCount($activePresenters);
            $pendingReplies = $existingAssistantMessages->count() < $expectedPresenterReplyCount;

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Message already submitted.',
                    'thread' => $thread->uuid,
                    'space' => $space->uuid,
                    'broadcast_channel' => $broadcastSpaceId,
                    'interaction_mode' => $observerPolicy['interaction_mode'],
                    'observer_status' => $observerPolicy['status'],
                    'text' => $firstAssistantMessage?->text,
                    'post_id' => $existingUserMessage->id,
                    'assistant_post_id' => $firstAssistantMessage?->id,
                    'assistant_posts' => $existingAssistantMessages
                        ->map(fn (Post $message): array => [
                            'id' => $message->id,
                            'actor_key' => data_get($message->meta, 'actor_key'),
                            'text' => $message->text,
                            'created_at' => optional($message->created_at)?->toIso8601String(),
                        ])
                        ->values()
                        ->all(),
                    'duplicate' => true,
                    'pending' => $pendingReplies,
                    'pending_presenters' => max($expectedPresenterReplyCount - $existingAssistantMessages->count(), 0),
                ],
            ];
        }

        $userMessage = $this->sendPeerThreadMessage->execute(
            space: $space,
            thread: $thread,
            actor: $actor,
            text: $content,
            attachments: $attachmentFiles,
            source: $activePresenters->isNotEmpty() ? 'agent_prompt' : 'peer_message',
            dispatchObservers: (bool) $observerPolicy['should_dispatch'],
        );
        $this->applyReceivedMessageA2uiMetadata->execute(
            $userMessage,
            $a2uiActions,
            $a2uiErrors,
            $a2uiClientDataModel,
            $a2uiClientCapabilities,
        );

        if ($activePresenters->isNotEmpty()) {
            $this->queuePresenterReplies->execute($thread, $userMessage, $actor, $activePresenters, $broadcastSpaceId);
        }

        $this->cacheIdempotentConversationMessage->execute($thread, $actor, $idempotencyKey, $userMessage);

        return [
            'status' => $activePresenters->isNotEmpty() ? 202 : 200,
            'body' => [
                'message' => $activePresenters->isNotEmpty() ? 'Agent response queued.' : 'Message sent.',
                'thread' => $thread->uuid,
                'space' => $space->uuid,
                'broadcast_channel' => $broadcastSpaceId,
                'interaction_mode' => $observerPolicy['interaction_mode'],
                'observer_status' => $observerPolicy['status'],
                'post_id' => $userMessage->id,
                'assistant_post_id' => null,
                'pending_presenters' => $activePresenters->isNotEmpty()
                    ? $this->expectedPresenterReplyCount($activePresenters)
                    : 0,
                'pending' => $activePresenters->isNotEmpty(),
                'message_action_accepted' => $a2uiActions !== [],
                'message_error_accepted' => $a2uiErrors !== [],
            ],
        ];
    }

    protected function broadcastSpaceIdForThread(Thread $thread): string
    {
        return "threads.{$thread->uuid}";
    }

    /**
     * @param  Collection<int, ThreadActor>  $activePresenters
     */
    protected function expectedPresenterReplyCount(Collection $activePresenters): int
    {
        return $activePresenters
            ->map(fn (ThreadActor $presenter): string => $presenter->actorName())
            ->filter(fn (?string $actorKey): bool => is_string($actorKey) && $actorKey !== '')
            ->unique()
            ->count();
    }
}
