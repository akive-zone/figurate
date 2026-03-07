<?php

namespace App\Http\Controllers\Server\Api;

use App\Actions\Server\Chat\ProjectAgentTurns;
use App\Actions\Server\Chat\ResolveChatChannelContext;
use App\Actions\Server\Chat\ResolveChatThreadContext;
use App\Actions\Server\Chat\SendPeerThreadMessage;
use App\Ai\Support\ChatAgentExecutor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Chat\StoreChatRequest;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use App\Support\A2ui\A2uiCatalogRegistry;
use App\Support\A2ui\A2uiPayloadContract;
use App\Support\Orchestrate\ConversationOrchestrator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class ChatController extends Controller
{
    public function __construct(
        protected A2uiPayloadContract $a2uiPayloadContract,
        protected A2uiCatalogRegistry $a2uiCatalogRegistry,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->cursorPageForRequest($request));
    }

    public function show(Request $request, string $chat, ProjectAgentTurns $projectAgentTurns): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        [$threadRecord, $channelRecord] = $this->resolveThreadForChat($chat, $actor);

        if (! $threadRecord) {
            return response()->json([
                'data' => [],
                'turns' => [],
                'chat' => [
                    'id' => $chat,
                    'channel_id' => $channelRecord?->uuid,
                    'thread_id' => null,
                ],
                'thread' => null,
            ]);
        }

        $threadMessages = $threadRecord->messages()
            ->orderBy('created_at')
            ->get();

        $messages = $threadMessages
            ->map(function (Message $message) use ($threadRecord): array {
                return [
                    'kind' => 'message',
                    'scope' => 'thread',
                    'thread_id' => $threadRecord->uuid,
                    'id' => $message->id,
                    'sender_name' => null,
                    'source' => data_get($message->meta, 'source'),
                    'is_agent' => data_get($message->meta, 'source') === 'agent_response',
                    'content' => $this->messageContent($message),
                    'extra' => $this->messageExtra($message),
                    'created_at' => optional($message->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $turns = ($projectAgentTurns)($threadRecord, $threadMessages, $actor);

        return response()->json([
            'data' => $messages,
            'turns' => $turns,
            'chat' => [
                'id' => $chat,
                'channel_id' => $channelRecord?->uuid,
                'thread_id' => $threadRecord->uuid,
            ],
            'thread' => [
                'id' => $threadRecord->uuid,
                'purpose' => $threadRecord->purpose,
                'phase' => $threadRecord->phase,
                'status' => $threadRecord->status,
            ],
        ]);
    }

    public function showMessageTurns(
        Request $request,
        string $chat,
        Message $message,
        ProjectAgentTurns $projectAgentTurns
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        [$threadRecord] = $this->resolveThreadForChat($chat, $actor);

        if (! $threadRecord) {
            abort(404, 'Thread not found.');
        }

        if (
            $message->messageable_type !== $threadRecord->getMorphClass()
            || $message->messageable_id !== $threadRecord->getKey()
        ) {
            abort(404, 'Message not found in this thread.');
        }

        $threadMessages = $threadRecord->messages()
            ->orderBy('created_at')
            ->get();
        $turns = collect(($projectAgentTurns)($threadRecord, $threadMessages, $actor))
            ->filter(fn (array $turn): bool => (int) ($turn['prompt_message_id'] ?? 0) === (int) $message->id)
            ->values()
            ->all();

        return response()->json([
            'data' => $turns,
            'thread' => $threadRecord->uuid,
            'message_id' => $message->id,
        ]);
    }

    public function store(
        StoreChatRequest $request,
        ConversationOrchestrator $orchestrator,
        ResolveChatChannelContext $resolveChatChannelContext,
        ResolveChatThreadContext $resolveChatThreadContext,
        SendPeerThreadMessage $sendPeerThreadMessage,
        ChatAgentExecutor $chatAgentExecutor,
    ): JsonResponse {
        $channelUuid = $request->validated('channel');
        $threadUuid = $request->validated('thread');
        $contentPayload = $request->validated('content');
        $extraPayload = $request->validated('extra');
        $extraPayload = is_array($extraPayload) ? $extraPayload : [];
        $a2uiActions = collect($contentPayload['actions'] ?? [])
            ->map(fn (mixed $action): ?array => is_array($action) ? $this->normalizeA2uiAction($action) : null)
            ->filter(fn (mixed $action): bool => is_array($action))
            ->values()
            ->all();
        $a2uiErrors = collect($contentPayload['errors'] ?? [])
            ->map(fn (mixed $error): ?array => is_array($error) ? $this->normalizeA2uiError($error) : null)
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
            [$channel, $thread] = $resolveChatThreadContext($threadUuid, $channelUuid);
        } else {
            $channel = $resolveChatChannelContext($channelUuid, $request->user());
        }

        Gate::authorize('view', $channel);
        Gate::authorize('create', Message::class);

        $normalizedRequestContent = $this->normalizedContentForStoreRequest($request, $a2uiActions, $a2uiErrors);

        $decision = $orchestrator->resolve(
            channel: $channel,
            actor: $request->user(),
            thread: $thread,
            message: $normalizedRequestContent,
        );
        $thread = $decision->thread;

        $activePresenters = $this->resolveActivePresenters($thread);

        if ($activePresenters->isNotEmpty()) {
            $broadcastChannelId = $this->broadcastChannelIdForThread($thread);
            $content = $normalizedRequestContent;

            if ($content === null) {
                abort(422, 'A text message is required for agent prompts.');
            }
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

                return response()->json([
                    'message' => 'Message already submitted.',
                    'thread' => $thread->uuid,
                    'channel' => $channel->uuid,
                    'broadcast_channel' => $broadcastChannelId,
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
                ]);
            }

            $userMessage = $sendPeerThreadMessage(
                channel: $channel,
                thread: $thread,
                actor: $actor,
                text: $content,
                attachments: collect(),
                source: 'agent_prompt',
                dispatchObservers: false,
            );
            $this->applyA2uiMetadata($userMessage, $a2uiActions, $a2uiErrors, $a2uiClientDataModel, $a2uiClientCapabilities);
            $activePresenters->each(function (ThreadActor $presenter) use (
                $chatAgentExecutor,
                $thread,
                $userMessage,
                $actor,
                $broadcastChannelId
            ): void {
                $chatAgentExecutor->queue(
                    thread: $thread,
                    userMessage: $userMessage,
                    user: $actor,
                    threadActor: $presenter,
                    broadcastChannelId: $broadcastChannelId,
                );
            });
            $this->cacheIdempotentMessage($thread, $actor, $idempotencyKey, $userMessage);

            return response()->json([
                'message' => 'Agent response queued.',
                'thread' => $thread->uuid,
                'channel' => $channel->uuid,
                'broadcast_channel' => $broadcastChannelId,
                'message_id' => $userMessage->id,
                'assistant_message_id' => null,
                'pending_presenters' => $this->expectedPresenterReplyCount($activePresenters),
                'pending' => true,
                'message_action_accepted' => $a2uiActions !== [],
                'message_error_accepted' => $a2uiErrors !== [],
            ], 202);
        }

        $actor = $request->user();
        $normalizedBody = $normalizedRequestContent;
        $attachmentFiles = collect($this->resolveAttachmentFiles($request))
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file): array => [
                'path' => (string) $file->getRealPath(),
                'original_name' => $file->getClientOriginalName(),
            ])
            ->filter(fn (array $attachment): bool => $attachment['path'] !== '' && $attachment['original_name'] !== '')
            ->values();

        $idempotencyKey = $this->idempotencyKey($request);
        $existingUserMessage = $this->findExistingUserMessage($thread, $actor, $idempotencyKey);

        if ($existingUserMessage) {
            if ($normalizedBody !== null && $existingUserMessage->text !== $normalizedBody) {
                $existingUserMessage->forceFill([
                    'text' => $normalizedBody,
                ])->save();
            }

            return response()->json([
                'message' => 'Message already submitted.',
                'channel' => $channel->uuid,
                'thread' => $thread->uuid,
                'message_id' => $existingUserMessage->id,
                'observer_status' => 'already_submitted',
                'duplicate' => true,
            ]);
        }

        $message = $sendPeerThreadMessage(
            channel: $channel,
            thread: $thread,
            actor: $actor,
            text: $normalizedBody,
            attachments: $attachmentFiles,
            source: 'peer_message',
            dispatchObservers: true,
        );
        $this->applyA2uiMetadata($message, $a2uiActions, $a2uiErrors, $a2uiClientDataModel, $a2uiClientCapabilities);
        $this->cacheIdempotentMessage($thread, $actor, $idempotencyKey, $message);

        return response()->json([
            'message' => 'Message sent.',
            'channel' => $channel->uuid,
            'thread' => $thread->uuid,
            'message_id' => $message->id,
            'observer_status' => 'queued',
            'message_action_accepted' => $a2uiActions !== [],
            'message_error_accepted' => $a2uiErrors !== [],
        ]);
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

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function cursorPageForRequest(Request $request): array
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return [
                'data' => [],
                'meta' => [
                    'next_cursor' => null,
                    'prev_cursor' => null,
                    'per_page' => 20,
                ],
            ];
        }

        $perPage = max(5, min(100, (int) $request->integer('per_page', 20)));
        $paginator = $this->queryVisibleChannels($actor)
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->query('cursor'));

        return [
            'data' => collect($paginator->items())
                ->map(fn (Channel $channel): array => $this->mapChatListItem($channel, $actor))
                ->values()
                ->all(),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $perPage,
            ],
        ];
    }

    protected function queryVisibleChannels(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Channel::class);

        $channelsQuery = Channel::query()->latest('created_at');

        if ($actor->type !== 'system') {
            $channelsQuery->whereHas('actorStates', function ($stateQuery) use ($actor): void {
                $stateQuery
                    ->where('actorable_type', $actor->getMorphClass())
                    ->where('actorable_id', $actor->id)
                    ->where('status', ChannelActorState::StatusActive);
            });
        }

        return $channelsQuery;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapChatListItem(Channel $channel, User $actor): array
    {
        $actorState = $this->actorStateForChannel($channel, $actor);
        $threadsPaginator = $this->recentThreadsQuery($channel, $actorState)
            ->cursorPaginate(5, ['*'], 'threads_cursor', null);
        $threads = collect($threadsPaginator->items());
        $latestMessageModel = $channel->latestConversationMessage();
        $activeThreadUuid = null;

        if (is_int($actorState?->thread_id) && $actorState->thread_id > 0) {
            $activeThreadUuid = Thread::query()
                ->whereKey($actorState->thread_id)
                ->value('uuid');
        }

        $latestMessage = null;
        if ($latestMessageModel) {
            $latestMessage = [
                'id' => $latestMessageModel->id,
                'content' => $this->messageContent($latestMessageModel),
                'extra' => $this->messageExtra($latestMessageModel),
                'created_at' => optional($latestMessageModel->created_at)?->toIso8601String(),
                'sender_name' => null,
            ];
        }

        return [
            'id' => $channel->uuid,
            'name' => $this->inferChatName($channel, $threads, $latestMessageModel?->text),
            'channel' => [
                'id' => $channel->uuid,
                'status' => $channel->status ?? 'open',
                'active_thread_id' => $activeThreadUuid,
                'last_message_at' => $latestMessage['created_at'] ?? optional($channel->created_at)?->toIso8601String(),
                'latest_message' => $latestMessage,
            ],
            'threads' => $threads
                ->map(fn (Thread $thread): array => $this->mapThreadListItem($thread, $actorState))
                ->values()
                ->all(),
            'threads_meta' => [
                'next_cursor' => $threadsPaginator->nextCursor()?->encode(),
                'prev_cursor' => $threadsPaginator->previousCursor()?->encode(),
                'per_page' => 5,
            ],
        ];
    }

    /**
     * @param  Collection<int, Thread>  $threads
     */
    protected function inferChatName(
        Channel $channel,
        Collection $threads,
        ?string $latestMessageBody
    ): string {
        $threadTitle = trim((string) ($threads->first()?->title ?? ''));
        if ($threadTitle !== '') {
            return $threadTitle;
        }

        $messagePreview = trim((string) ($latestMessageBody ?? ''));
        if ($messagePreview !== '') {
            return mb_substr($messagePreview, 0, 60);
        }

        return sprintf('Chat %s', mb_substr($channel->uuid, 0, 8));
    }

    protected function actorStateForChannel(Channel $channel, User $actor): ?ChannelActorState
    {
        return $channel->actorStates()
            ->where('actorable_type', $actor->getMorphClass())
            ->where('actorable_id', $actor->id)
            ->where('status', ChannelActorState::StatusActive)
            ->latest('updated_at')
            ->first();
    }

    protected function recentThreadsQuery(Channel $channel, ?ChannelActorState $actorState): Builder
    {
        $threadIds = $channel->conversationThreadIds();
        $query = Thread::query()
            ->whereIn('id', $threadIds->all())
            ->withMax('messages', 'created_at');

        if (is_int($actorState?->thread_id) && $actorState->thread_id > 0) {
            $query->orderByRaw('case when id = ? then 0 else 1 end', [$actorState->thread_id]);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapThreadListItem(Thread $thread, ?ChannelActorState $actorState): array
    {
        return [
            'id' => $thread->uuid,
            'title' => $thread->title ?: 'Thread',
            'purpose' => $thread->purpose,
            'status' => $thread->status,
            'created_at' => optional($thread->created_at)?->toIso8601String(),
            'last_message_at' => $this->formatIso8601($thread->messages_max_created_at),
            'is_active_for_actor' => is_int($actorState?->thread_id) && $actorState->thread_id === $thread->id,
        ];
    }

    protected function formatIso8601(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return null;
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
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>|null
     */
    protected function normalizeA2uiAction(array $action): ?array
    {
        return $this->a2uiPayloadContract->normalizeAction($action);
    }

    /**
     * @param  array<string, mixed>  $error
     * @return array<string, mixed>|null
     */
    protected function normalizeA2uiError(array $error): ?array
    {
        return $this->a2uiPayloadContract->normalizeError($error);
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageContent(Message $message): array
    {
        return [
            'text' => is_string($message->text) ? $message->text : '',
            'attachments' => is_array($message->attachments) ? $message->attachments : [],
            'actions' => is_array($message->actions) ? $message->actions : [],
            'errors' => is_array($message->errors) ? $message->errors : [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function messageExtra(Message $message): ?array
    {
        $surface = data_get($message->meta, 'a2ui');
        $surface = is_array($surface) ? $surface : null;
        $dataModel = $this->trimmedString(data_get($message->meta, 'a2ui_client_data_model'));
        $capabilities = $this->a2uiPayloadContract->normalizeClientCapabilities(
            is_array(data_get($message->meta, 'a2ui_client_capabilities'))
                ? data_get($message->meta, 'a2ui_client_capabilities')
                : null
        );

        if ($surface === null && $dataModel === null && $capabilities === null) {
            return null;
        }

        if (is_array($surface)) {
            $surface = $this->a2uiCatalogRegistry->decoratePayload($surface, $capabilities);
        }

        return [
            'a2ui' => [
                'surface' => $surface,
                'config' => [
                    'a2uiClientDataModel' => $dataModel,
                    'a2uiClientCapabilities' => $capabilities,
                ],
            ],
        ];
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

    /**
     * @return array{0: ?Thread, 1: ?Channel}
     */
    protected function resolveThreadForChat(string $chat, User $actor): array
    {
        $threadRecord = Thread::query()
            ->where('uuid', $chat)
            ->first();

        if ($threadRecord instanceof Thread) {
            Gate::forUser($actor)->authorize('view', $threadRecord);

            $channelRecord = null;
            if ($threadRecord->threadable instanceof Channel) {
                $channelRecord = $threadRecord->threadable;
                Gate::forUser($actor)->authorize('view', $channelRecord);
            }

            return [$threadRecord, $channelRecord];
        }

        $channelRecord = Channel::query()
            ->where('uuid', $chat)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $channelRecord);

        $threadIds = $channelRecord->conversationThreadIds();

        if ($threadIds->isEmpty()) {
            return [null, $channelRecord];
        }

        $actorStateThreadId = $channelRecord->actorStates()
            ->where('actorable_type', $actor->getMorphClass())
            ->where('actorable_id', $actor->id)
            ->where('status', ChannelActorState::StatusActive)
            ->value('thread_id');

        if (is_int($actorStateThreadId) && $actorStateThreadId > 0 && $threadIds->contains($actorStateThreadId)) {
            $activeThread = Thread::query()
                ->whereKey($actorStateThreadId)
                ->first();

            if ($activeThread instanceof Thread) {
                Gate::forUser($actor)->authorize('view', $activeThread);

                return [$activeThread, $channelRecord];
            }
        }

        $latestThread = Thread::query()
            ->whereIn('id', $threadIds->all())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latestThread instanceof Thread) {
            Gate::forUser($actor)->authorize('view', $latestThread);
        }

        return [$latestThread, $channelRecord];
    }
}
