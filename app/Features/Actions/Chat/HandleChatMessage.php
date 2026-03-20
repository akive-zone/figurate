<?php

namespace App\Features\Actions\Chat;

use App\Ai\Support\A2ui\A2uiPayloadContract;
use App\Ai\Support\ChatAgentExecutor;
use App\Http\Requests\Server\Chat\StoreChatRequest;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use App\Support\Orchestrate\ConversationOrchestrator;
use App\Support\Orchestrate\ResolveObserverDispatchPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class HandleChatMessage
{
    public function __construct(
        protected A2uiPayloadContract $a2uiPayloadContract,
        protected ConversationOrchestrator $orchestrator,
        protected ResolveChatChannelContext $resolveChatChannelContext,
        protected ResolveChatThreadContext $resolveChatThreadContext,
        protected SendPeerThreadMessage $sendPeerThreadMessage,
        protected ChatAgentExecutor $chatAgentExecutor,
        protected ResolveObserverDispatchPolicy $resolveObserverDispatchPolicy,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function __invoke(StoreChatRequest $request): array
    {
        $channelUuid = $request->validated('channel');
        $threadUuid = $request->validated('thread');
        $contentPayload = $request->validated('content');
        $extraPayload = $request->validated('extra');
        $extraPayload = is_array($extraPayload) ? $extraPayload : [];
        $a2uiActions = collect($contentPayload['actions'] ?? [])
            ->map(fn (mixed $action): ?array => is_array($action) ? $this->a2uiPayloadContract->normalizeAction($action) : null)
            ->filter(fn (mixed $action): bool => is_array($action))
            ->values()
            ->all();
        $a2uiErrors = collect($contentPayload['errors'] ?? [])
            ->map(fn (mixed $error): ?array => is_array($error) ? $this->a2uiPayloadContract->normalizeError($error) : null)
            ->filter(fn (mixed $error): bool => is_array($error))
            ->values()
            ->all();
        $a2uiClientDataModel = $this->trimmedString(data_get($extraPayload, 'a2ui.config.a2uiClientDataModel'));
        $a2uiClientCapabilities = $this->a2uiPayloadContract->normalizeClientCapabilities(
            is_array(data_get($extraPayload, 'a2ui.config.a2uiClientCapabilities'))
                ? data_get($extraPayload, 'a2ui.config.a2uiClientCapabilities')
                : null
        );
        $thread = null;

        if (is_string($threadUuid) && $threadUuid !== '') {
            [$channel, $thread] = ($this->resolveChatThreadContext)($threadUuid, $channelUuid);
        } else {
            $channel = ($this->resolveChatChannelContext)($channelUuid, $request->user());
        }

        Gate::authorize('view', $channel);
        Gate::authorize('create', Message::class);

        $normalizedRequestContent = $this->normalizedContentForStoreRequest($request, $a2uiActions, $a2uiErrors);

        $decision = $this->orchestrator->resolve(
            channel: $channel,
            actor: $request->user(),
            thread: $thread,
            message: $normalizedRequestContent,
        );
        $thread = $decision->thread;

        $observerPolicy = $this->resolveObserverDispatchPolicy->forThread($thread);
        $activePresenters = $this->resolveActivePresenters($thread);
        $attachmentFiles = collect($this->resolveAttachmentFiles($request))
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file): array => [
                'path' => (string) $file->getRealPath(),
                'original_name' => $file->getClientOriginalName(),
            ])
            ->filter(fn (array $attachment): bool => $attachment['path'] !== '' && $attachment['original_name'] !== '')
            ->values();

        $broadcastChannelId = $this->broadcastChannelIdForThread($thread);
        $content = $normalizedRequestContent;

        if ($content === null) {
            abort(422, 'A text message is required for chat submission.');
        }

        /** @var User $actor */
        $actor = $request->user();
        $idempotencyKey = $this->idempotencyKey($request);
        $existingUserMessage = $this->findExistingUserMessage($thread, $actor, $idempotencyKey);

        if ($existingUserMessage) {
            if ($existingUserMessage->text !== $content) {
                $existingUserMessage->forceFill([
                    'text' => $content,
                ])->save();
            }

            $existingAssistantMessages = $this->findAssistantRepliesForMessage($thread, $existingUserMessage, $activePresenters);
            $firstAssistantMessage = $existingAssistantMessages->first();
            $expectedPresenterReplyCount = $this->expectedPresenterReplyCount($activePresenters);
            $pendingReplies = $existingAssistantMessages->count() < $expectedPresenterReplyCount;

            return [
                'status' => $activePresenters->isNotEmpty() ? 200 : 200,
                'body' => [
                    'message' => 'Message already submitted.',
                    'thread' => $thread->uuid,
                    'channel' => $channel->uuid,
                    'broadcast_channel' => $broadcastChannelId,
                    'interaction_mode' => $observerPolicy['interaction_mode'],
                    'observer_status' => $observerPolicy['status'],
                    'text' => $firstAssistantMessage?->text,
                    'message_id' => $existingUserMessage->id,
                    'assistant_message_id' => $firstAssistantMessage?->id,
                    'assistant_messages' => $existingAssistantMessages
                        ->map(fn (Message $message): array => [
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

        $userMessage = ($this->sendPeerThreadMessage)(
            channel: $channel,
            thread: $thread,
            actor: $actor,
            text: $content,
            attachments: $attachmentFiles,
            source: $activePresenters->isNotEmpty() ? 'agent_prompt' : 'peer_message',
            dispatchObservers: (bool) $observerPolicy['should_dispatch'],
        );
        $this->applyA2uiMetadata($userMessage, $a2uiActions, $a2uiErrors, $a2uiClientDataModel, $a2uiClientCapabilities);

        if ($activePresenters->isNotEmpty()) {
            $activePresenters->each(function (ThreadActor $presenter) use (
                $thread,
                $userMessage,
                $actor,
                $broadcastChannelId
            ): void {
                $this->chatAgentExecutor->queue(
                    thread: $thread,
                    userMessage: $userMessage,
                    user: $actor,
                    threadActor: $presenter,
                    broadcastChannelId: $broadcastChannelId,
                );
            });
        }

        $this->cacheIdempotentMessage($thread, $actor, $idempotencyKey, $userMessage);

        return [
            'status' => $activePresenters->isNotEmpty() ? 202 : 200,
            'body' => [
                'message' => $activePresenters->isNotEmpty() ? 'Agent response queued.' : 'Message sent.',
                'thread' => $thread->uuid,
                'channel' => $channel->uuid,
                'broadcast_channel' => $broadcastChannelId,
                'interaction_mode' => $observerPolicy['interaction_mode'],
                'observer_status' => $observerPolicy['status'],
                'message_id' => $userMessage->id,
                'assistant_message_id' => null,
                'pending_presenters' => $activePresenters->isNotEmpty()
                    ? $this->expectedPresenterReplyCount($activePresenters)
                    : 0,
                'pending' => $activePresenters->isNotEmpty(),
                'message_action_accepted' => $a2uiActions !== [],
                'message_error_accepted' => $a2uiErrors !== [],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $a2uiActions
     * @param  array<int, array<string, mixed>>  $a2uiErrors
     */
    protected function normalizedContentForStoreRequest(StoreChatRequest $request, array $a2uiActions, array $a2uiErrors): ?string
    {
        $text = data_get($request->validated('content'), 'text');
        $normalizedText = is_string($text) ? trim($text) : null;
        $normalizedText = $normalizedText === '' ? null : $normalizedText;

        if ($normalizedText !== null) {
            return $normalizedText;
        }

        return $this->composeA2uiFallbackBody($a2uiActions, $a2uiErrors);
    }

    /**
     * @param  array<int, array<string, mixed>>  $a2uiActions
     * @param  array<int, array<string, mixed>>  $a2uiErrors
     */
    protected function composeA2uiFallbackBody(array $a2uiActions, array $a2uiErrors): ?string
    {
        if ($a2uiActions !== []) {
            $firstAction = collect($a2uiActions)->first(fn (mixed $action): bool => is_array($action));

            if (! is_array($firstAction)) {
                return 'A2UI actions submitted.';
            }

            $actionName = $this->trimmedString($firstAction['name'] ?? null);

            if ($actionName === null) {
                return 'A2UI actions submitted.';
            }

            return "A2UI actions submitted: {$actionName}";
        }

        if ($a2uiErrors !== []) {
            $firstError = collect($a2uiErrors)->first(fn (mixed $error): bool => is_array($error));

            if (! is_array($firstError)) {
                return 'A2UI client errors reported.';
            }

            $errorMessage = $this->trimmedString($firstError['message'] ?? null);
            $errorCode = $this->trimmedString($firstError['code'] ?? null);

            if ($errorMessage !== null) {
                return "A2UI client error: {$errorMessage}";
            }

            if ($errorCode !== null) {
                return "A2UI client error code: {$errorCode}";
            }

            return 'A2UI client errors reported.';
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $a2uiActions
     * @param  array<int, array<string, mixed>>  $a2uiErrors
     * @param  array<string, mixed>|null  $a2uiClientCapabilities
     */
    protected function applyA2uiMetadata(
        Message $message,
        array $a2uiActions,
        array $a2uiErrors,
        ?string $a2uiClientDataModel,
        ?array $a2uiClientCapabilities,
    ): void {
        if ($a2uiActions === [] && $a2uiErrors === [] && $a2uiClientDataModel === null && $a2uiClientCapabilities === null) {
            return;
        }

        $meta = is_array($message->meta) ? $message->meta : [];

        if ($a2uiClientDataModel !== null) {
            $meta['a2ui_client_data_model'] = $a2uiClientDataModel;
        }
        if (is_array($a2uiClientCapabilities)) {
            $meta['a2ui_client_capabilities'] = $a2uiClientCapabilities;
        }
        $meta['a2ui_actions_received_at'] = now()->toIso8601String();

        $message->forceFill([
            'actions' => $a2uiActions !== [] ? $a2uiActions : $message->actions,
            'errors' => $a2uiErrors !== [] ? $a2uiErrors : $message->errors,
            'meta' => $meta,
        ])->save();
    }

    /**
     * @return Collection<int, ThreadActor>
     */
    protected function resolveActivePresenters(Thread $thread): Collection
    {
        return $thread->actors()
            ->where('role', ThreadActor::RolePresenter)
            ->where('status', ThreadActor::StatusActive)
            ->orderBy('priority')
            ->get();
    }

    protected function broadcastChannelIdForThread(Thread $thread): string
    {
        return "threads.{$thread->uuid}";
    }

    protected function idempotencyKey(StoreChatRequest $request): ?string
    {
        $rawValue = $request->header('X-Idempotency-Key');

        if (! is_string($rawValue)) {
            return null;
        }

        $key = trim($rawValue);

        if ($key === '') {
            return null;
        }

        return mb_substr($key, 0, 120);
    }

    protected function findExistingUserMessage(Thread $thread, User $actor, ?string $idempotencyKey): ?Message
    {
        if (! $idempotencyKey) {
            return null;
        }

        $messageId = Cache::get($this->cacheKeyForIdempotency($thread, $actor, $idempotencyKey));

        if (is_string($messageId) && ctype_digit($messageId)) {
            $messageId = (int) $messageId;
        }

        if (! is_int($messageId) || $messageId <= 0) {
            return null;
        }

        return Message::query()
            ->whereKey($messageId)
            ->where('messageable_type', $thread->getMorphClass())
            ->where('messageable_id', $thread->getKey())
            ->where('senderable_type', $actor->getMorphClass())
            ->where('senderable_id', $actor->getKey())
            ->first();
    }

    /**
     * @param  Collection<int, ThreadActor>  $activePresenters
     * @return Collection<int, Message>
     */
    protected function findAssistantRepliesForMessage(
        Thread $thread,
        Message $userMessage,
        Collection $activePresenters
    ): Collection {
        $presenterActorKeys = $activePresenters
            ->map(fn (ThreadActor $presenter): ?string => $presenter->actorName())
            ->filter(fn (mixed $actorKey): bool => is_string($actorKey) && $actorKey !== '')
            ->values()
            ->all();

        if ($presenterActorKeys === []) {
            return collect();
        }

        return Message::query()
            ->where('messageable_type', $thread->getMorphClass())
            ->where('messageable_id', $thread->getKey())
            ->whereNull('senderable_type')
            ->whereNull('senderable_id')
            ->where('meta->source', 'agent_response')
            ->whereIn('meta->actor_key', $presenterActorKeys)
            ->where('id', '>', $userMessage->id)
            ->oldest('id')
            ->get();
    }

    /**
     * @param  Collection<int, ThreadActor>  $activePresenters
     */
    protected function expectedPresenterReplyCount(Collection $activePresenters): int
    {
        return $activePresenters
            ->map(fn (ThreadActor $presenter): ?string => $presenter->actorName())
            ->filter(fn (mixed $actorKey): bool => is_string($actorKey) && $actorKey !== '')
            ->unique()
            ->count();
    }

    protected function cacheIdempotentMessage(Thread $thread, User $actor, ?string $idempotencyKey, Message $message): void
    {
        if (! $idempotencyKey) {
            return;
        }

        Cache::put(
            $this->cacheKeyForIdempotency($thread, $actor, $idempotencyKey),
            $message->getKey(),
            now()->addHours(24),
        );
    }

    protected function cacheKeyForIdempotency(Thread $thread, User $actor, string $idempotencyKey): string
    {
        return sprintf(
            'chat:idempotency:%d:%s:%d:%s',
            $thread->getKey(),
            $actor->getMorphClass(),
            $actor->getKey(),
            sha1($idempotencyKey),
        );
    }

    protected function trimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<int, mixed>
     */
    protected function resolveAttachmentFiles(StoreChatRequest $request): array
    {
        $attachments = [];
        $contentAttachments = $request->file('content.attachments', []);

        if (is_array($contentAttachments)) {
            $attachments = [...$attachments, ...$contentAttachments];
        } elseif ($contentAttachments instanceof UploadedFile) {
            $attachments[] = $contentAttachments;
        }

        return $attachments;
    }
}
