<?php

namespace App\Features\Operations\Chat;

use App\Events\Server\Chat\PreparingMessageResponse;
use App\Features\Actions\Chat\ApplyReceivedMessageData;
use App\Features\Actions\Chat\CacheIdempotentConversationMessage;
use App\Features\Actions\Chat\FindExistingIdempotentConversationMessage;
use App\Features\Actions\Chat\NormalizeInboundConversationPayload;
use App\Features\Actions\Chat\ResolveConversationAttachments;
use App\Features\Actions\Chat\ResolveConversationIdempotencyKey;
use App\Features\Actions\Chat\ResolveConversationSpaceContext;
use App\Features\Actions\Chat\ResolveConversationThreadContext;
use App\Features\Actions\Chat\SendPeerThreadMessage;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\Gate;

class SubmitChatMessageOperation
{
    public function __construct(
        protected ResolveConversationThreadOperation $resolveConversationThreadOperation,
        protected ResolveConversationSpaceContext $resolveConversationSpaceContext,
        protected ResolveConversationThreadContext $resolveConversationThreadContext,
        protected NormalizeInboundConversationPayload $normalizeInboundConversationPayload,
        protected SendPeerThreadMessage $sendPeerThreadMessage,
        protected ApplyReceivedMessageData $applyReceivedMessageData,
        protected ResolveConversationAttachments $resolveConversationAttachments,
        protected ResolveConversationIdempotencyKey $resolveConversationIdempotencyKey,
        protected FindExistingIdempotentConversationMessage $findExistingIdempotentConversationMessage,
        protected CacheIdempotentConversationMessage $cacheIdempotentConversationMessage,
    ) {}

    /**
     * @param  array{
     *     actor: User,
     *     space?: mixed,
     *     thread?: mixed,
     *     content?: mixed,
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
        $normalizedPayload = $this->normalizeInboundConversationPayload->execute(
            is_array($contentPayload) ? $contentPayload : [],
        );
        $actions = $normalizedPayload['actions'];
        $errors = $normalizedPayload['errors'];
        $thread = null;

        if (is_string($threadUuid) && $threadUuid !== '') {
            [$space, $thread] = $this->resolveConversationThreadContext->execute($threadUuid, $spaceUuid);
        } else {
            $space = $this->resolveConversationSpaceContext->execute($spaceUuid, $actor);
        }

        Gate::authorize('view', $space);
        Gate::authorize('create', Post::class);

        $normalizedRequestContent = $normalizedPayload['text'];

        $thread = $this->resolveConversationThreadOperation->run(
            space: $space,
            actor: $actor,
            thread: $thread,
        );

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

            return $this->prepareResponse(
                post: $existingUserMessage,
                thread: $thread,
                space: $space,
                actor: $actor,
                status: 200,
                body: [
                    'message' => 'Message already submitted.',
                    'thread' => $thread->uuid,
                    'space' => $space->uuid,
                    'broadcast_channel' => $broadcastSpaceId,
                    'post_id' => $existingUserMessage->ulid,
                    'duplicate' => true,
                ],
                duplicate: true,
            );
        }

        $userMessage = $this->sendPeerThreadMessage->execute(
            space: $space,
            thread: $thread,
            actor: $actor,
            text: $content,
            attachments: $attachmentFiles,
            source: 'peer_message',
        );
        $this->applyReceivedMessageData->execute(
            $userMessage,
            $actions,
            $errors,
        );

        $this->cacheIdempotentConversationMessage->execute($thread, $actor, $idempotencyKey, $userMessage);

        return $this->prepareResponse(
            post: $userMessage,
            thread: $thread,
            space: $space,
            actor: $actor,
            status: 200,
            body: [
                'message' => 'Message sent.',
                'thread' => $thread->uuid,
                'space' => $space->uuid,
                'broadcast_channel' => $broadcastSpaceId,
                'post_id' => $userMessage->ulid,
                'message_action_accepted' => $actions !== [],
                'message_error_accepted' => $errors !== [],
            ],
        );
    }

    protected function broadcastSpaceIdForThread(Thread $thread): string
    {
        return "threads.{$thread->uuid}";
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function prepareResponse(
        Post $post,
        Thread $thread,
        Space $space,
        User $actor,
        int $status,
        array $body,
        bool $duplicate = false,
    ): array {
        $event = new PreparingMessageResponse(
            post: $post,
            thread: $thread,
            space: $space,
            actor: $actor,
            body: $body,
            status: $status,
            duplicate: $duplicate,
        );
        event($event);

        return [
            'status' => $event->status,
            'body' => $event->body,
        ];
    }
}
