<?php

namespace App\Http\Controllers\Server\Api;

use App\Ai\Agents\OrderAgent;
use App\Ai\Agents\RequestAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\StoreChatRequest;
use App\Jobs\ProcessThreadObservers;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorMemory;
use App\Models\Server\User;
use App\Support\Conversation\ConversationOrchestrator;
use App\Support\Signal\SidebarChats;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Exceptions\RateLimitedException;

class ChatController extends Controller
{
    public function index(Request $request, SidebarChats $sidebarChats): JsonResponse
    {
        return response()->json($sidebarChats->cursorPageForRequest($request));
    }

    public function show(Request $request, string $thread): JsonResponse
    {
        $threadRecord = Thread::query()
            ->where('uuid', $thread)
            ->firstOrFail();

        Gate::authorize('view', $threadRecord);

        $messages = $threadRecord->messages()
            ->orderBy('created_at')
            ->get()
            ->map(function (Message $message) use ($threadRecord): array {
                return [
                    'kind' => 'message',
                    'scope' => 'thread',
                    'thread_id' => $threadRecord->uuid,
                    'id' => $message->id,
                    'sender_name' => null,
                    'content' => $message->body,
                    'attachments' => is_array($message->attachments) ? $message->attachments : [],
                    'created_at' => optional($message->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => $messages,
            'thread' => [
                'id' => $threadRecord->uuid,
                'purpose' => $threadRecord->purpose,
                'phase' => $threadRecord->phase,
                'status' => $threadRecord->status,
            ],
        ]);
    }

    public function store(
        StoreChatRequest $request,
        ConversationOrchestrator $orchestrator
    ): JsonResponse {
        [$channel, $serviceRequest] = $this->resolveChannelContext($request);

        Gate::authorize('view', $channel);
        Gate::authorize('create', Message::class);

        $decision = $orchestrator->resolve(
            channel: $channel,
            serviceRequest: $serviceRequest,
            actor: $request->user(),
            requestedThreadId: $this->resolveRequestedThreadId($request, $channel, $serviceRequest),
            message: $request->validated('content'),
        );
        $thread = $decision->thread;

        $primaryHandler = $this->resolvePrimaryHandlerActor($thread);

        if ($primaryHandler->actorName() !== ThreadActor::ActorHumanChat) {
            $content = $request->validated('content');

            if (! is_string($content) || trim($content) === '') {
                abort(422, 'A text message is required for agent prompts.');
            }
        }

        return match ($primaryHandler->actorName()) {
            ThreadActor::ActorHumanChat => $this->storeHumanMessage($request, $channel, $serviceRequest, $thread),
            default => $this->promptAgentThread($request, $channel, $serviceRequest, $thread, $request->user()),
        };
    }

    /**
     * @return array{0: Channel, 1: ServiceRequest|null}
     */
    protected function resolveChannelContext(StoreChatRequest $request): array
    {
        $channelUuid = $request->validated('channel');

        if (is_string($channelUuid) && $channelUuid !== '') {
            $channel = Channel::query()->where('uuid', $channelUuid)->firstOrFail();
            $serviceRequest = $channel->requests()->first();

            return [$channel, $serviceRequest];
        }

        return $this->bootstrapChannelContext($request);
    }

    protected function resolveRequestedThreadId(
        StoreChatRequest $request,
        Channel $channel,
        ?ServiceRequest $serviceRequest
    ): ?int {
        $threadUuid = $request->validated('thread');

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

    /**
     * @return array{0: Channel, 1: ServiceRequest|null}
     */
    protected function bootstrapChannelContext(StoreChatRequest $request): array
    {
        Gate::authorize('create', Channel::class);

        $actor = $request->user();

        return DB::transaction(function () use ($actor): array {
            $channel = Channel::query()->create([
                'status' => 'open',
            ]);

            $mainThread = $channel->threads()->create([
                'purpose' => Thread::PurposeMain,
                'title' => 'Project Main',
                'phase' => 'request_intake',
                'status' => 'open',
            ]);

            $mainThread->actors()->create([
                'actorable_type' => ThreadActor::ActorRequestAgent,
                'actorable_id' => null,
                'role' => ThreadActor::RoleHandler,
                'status' => ThreadActor::StatusActive,
                'priority' => 1,
                'config' => null,
            ]);

            ChannelActorState::query()->updateOrCreate(
                [
                    'channel_id' => $channel->id,
                    'actor_type' => $actor->getMorphClass(),
                    'actor_id' => $actor->getKey(),
                ],
                [
                    'thread_id' => $mainThread->id,
                    'status' => ChannelActorState::StatusActive,
                ],
            );

            return [$channel, null];
        });
    }

    protected function resolvePrimaryHandlerActor(Thread $thread): ThreadActor
    {
        $actor = $thread->primaryHandlerActor()->first();

        if (! $actor) {
            abort(422, 'Thread has no active primary handler.');
        }

        return $actor;
    }

    protected function storeHumanMessage(
        StoreChatRequest $request,
        Channel $channel,
        ?ServiceRequest $serviceRequest,
        Thread $thread
    ): JsonResponse {
        if (! $this->canActorWrite($channel, $serviceRequest, $request->user())) {
            abort(403);
        }

        /** @var Collection<int, UploadedFile> $uploadedMedia */
        $uploadedMedia = collect($request->file('contents', []))
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->values();

        $content = $request->validated('content');
        $idempotencyKey = $this->idempotencyKey($request);
        $existingUserMessage = $this->findExistingUserMessage($thread, $request->user(), $idempotencyKey);

        if ($existingUserMessage) {
            $normalizedContent = is_string($content) ? trim($content) : null;
            if ($normalizedContent !== null && $normalizedContent !== '' && $existingUserMessage->body !== $normalizedContent) {
                $existingUserMessage->forceFill([
                    'body' => $normalizedContent,
                ])->save();
            }

            return response()->json([
                'message' => 'Message already submitted.',
                'channel' => $channel->uuid,
                'thread' => $thread->uuid,
                'message_id' => $existingUserMessage->id,
                'observer_status' => 'already_submitted',
                'mode' => 'human_chat',
                'duplicate' => true,
            ]);
        }

        $message = $thread->messages()->create([
            'senderable_type' => $request->user()->getMorphClass(),
            'senderable_id' => $request->user()->getKey(),
            'type' => 'text',
            'body' => is_string($content) && $content !== '' ? $content : null,
            'attachments' => null,
            'meta' => [
                'source' => 'human_chat',
            ],
        ]);

        $this->cacheIdempotentMessage($thread, $request->user(), $idempotencyKey, $message);

        $uploadedMedia->each(function (UploadedFile $file) use ($message): void {
            $message->addMedia($file)
                ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('attachments');
        });

        if ($uploadedMedia->isNotEmpty()) {
            $message->syncAttachmentPayload();
        }

        ProcessThreadObservers::dispatch($thread->id, $message->id);

        return response()->json([
            'message' => 'Message sent.',
            'channel' => $channel->uuid,
            'thread' => $thread->uuid,
            'message_id' => $message->id,
            'observer_status' => 'queued',
            'mode' => 'human_chat',
        ]);
    }

    protected function promptAgentThread(
        StoreChatRequest $request,
        Channel $channel,
        ?ServiceRequest $serviceRequest,
        Thread $thread,
        User $actor
    ): JsonResponse {
        if (! $this->canActorWrite($channel, $serviceRequest, $actor)) {
            abort(403);
        }

        $content = $request->validated('content');
        $content = is_string($content) ? trim($content) : '';
        if ($content === '') {
            abort(422, 'A text message is required for agent prompts.');
        }
        $idempotencyKey = $this->idempotencyKey($request);

        $primaryHandler = $this->resolvePrimaryHandlerActor($thread);
        $agent = $this->resolveAgent($primaryHandler, $actor);
        $memory = $this->resolveMemory($thread, $primaryHandler);
        $existingUserMessage = $this->findExistingUserMessage($thread, $actor, $idempotencyKey);

        if ($existingUserMessage) {
            if ($existingUserMessage->body !== $content) {
                $existingUserMessage->forceFill([
                    'body' => $content,
                ])->save();
            }

            $existingAssistantMessage = $this->findAssistantReplyForMessage($thread, $existingUserMessage, $primaryHandler);

            return response()->json([
                'message' => 'Message already submitted.',
                'thread' => $thread->uuid,
                'channel' => $channel->uuid,
                'conversation_id' => $memory->conversation_id,
                'text' => $existingAssistantMessage?->body,
                'message_id' => $existingUserMessage->id,
                'assistant_message_id' => $existingAssistantMessage?->id,
                'mode' => 'agent',
                'duplicate' => true,
                'pending' => $existingAssistantMessage === null,
            ]);
        }

        $userMessage = $thread->messages()->create([
            'senderable_type' => $actor->getMorphClass(),
            'senderable_id' => $actor->getKey(),
            'type' => 'text',
            'body' => $content,
            'attachments' => null,
            'meta' => [
                'source' => 'agent_prompt',
            ],
        ]);

        $this->cacheIdempotentMessage($thread, $actor, $idempotencyKey, $userMessage);

        if ($memory->conversation_id) {
            $agent->continue($memory->conversation_id, $actor);
        } else {
            $agent->forUser($actor);
        }

        try {
            $response = $agent->prompt($content);
        } catch (RateLimitedException) {
            return response()->json([
                'message' => 'AI provider is rate limited. Please retry shortly.',
                'thread' => $thread->uuid,
                'channel' => $channel->uuid,
                'mode' => 'agent',
                'error_code' => 'ai_rate_limited',
                'retryable' => true,
            ], 429);
        } catch (HttpClientException) {
            return response()->json([
                'message' => 'AI provider request failed. Please retry shortly.',
                'thread' => $thread->uuid,
                'channel' => $channel->uuid,
                'mode' => 'agent',
                'error_code' => 'ai_provider_unavailable',
                'retryable' => true,
            ], 503);
        }

        if ($response->conversationId) {
            $memory->forceFill([
                'conversation_id' => $response->conversationId,
                'last_used_at' => now(),
            ])->save();
        }

        $assistantText = is_string($response->text) ? trim($response->text) : '';
        $assistantMessage = null;
        if ($assistantText !== '') {
            $assistantMessage = $thread->messages()->create([
                'senderable_type' => null,
                'senderable_id' => null,
                'type' => 'text',
                'body' => $assistantText,
                'attachments' => null,
                'meta' => [
                    'source' => 'agent_response',
                    'actor_key' => $primaryHandler->actorName(),
                    'conversation_id' => $response->conversationId ?? $memory->conversation_id,
                ],
            ]);
        }

        return response()->json([
            'message' => 'Agent responded.',
            'thread' => $thread->uuid,
            'channel' => $channel->uuid,
            'conversation_id' => $response->conversationId ?? $memory->conversation_id,
            'text' => $response->text,
            'message_id' => $userMessage->id,
            'assistant_message_id' => $assistantMessage?->id,
            'mode' => 'agent',
        ]);
    }

    protected function resolveMemory(Thread $thread, ThreadActor $primaryHandler): ThreadActorMemory
    {
        return ThreadActorMemory::query()->firstOrCreate(
            [
                'thread_id' => $thread->id,
                'thread_actor_id' => $primaryHandler->id,
                'provider' => 'default',
                'model' => 'default',
            ],
            [
                'conversation_id' => null,
                'state' => null,
                'last_used_at' => null,
            ],
        );
    }

    protected function resolveAgent(ThreadActor $primaryHandler, User $actor): Agent
    {
        $thread = $primaryHandler->thread;

        return match ($primaryHandler->actorName()) {
            ThreadActor::ActorOrderAgent => OrderAgent::make(thread: $thread, actor: $actor),
            default => RequestAgent::make(thread: $thread, actor: $actor),
        };
    }

    protected function canActorWrite(Channel $channel, ?ServiceRequest $serviceRequest, User $actor): bool
    {
        if ($serviceRequest) {
            return $serviceRequest->hasParticipant($actor);
        }

        return $channel->hasActor($actor);
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

    protected function findAssistantReplyForMessage(Thread $thread, Message $userMessage, ThreadActor $primaryHandler): ?Message
    {
        return Message::query()
            ->where('messageable_type', $thread->getMorphClass())
            ->where('messageable_id', $thread->getKey())
            ->whereNull('senderable_type')
            ->whereNull('senderable_id')
            ->where('meta->source', 'agent_response')
            ->where('meta->actor_key', $primaryHandler->actorName())
            ->where('id', '>', $userMessage->id)
            ->oldest('id')
            ->first();
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
}
