<?php

namespace App\Http\Controllers\Server\Api;

use App\Actions\Server\Chat\ResolveChatChannelContext;
use App\Actions\Server\Chat\ResolveChatRequestedThreadId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\StoreChatRequest;
use App\Jobs\GenerateAgentReply;
use App\Jobs\ProcessThreadObservers;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use App\Support\Conversation\ConversationOrchestrator;
use App\Support\Signal\SidebarChats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

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
        ConversationOrchestrator $orchestrator,
        ResolveChatChannelContext $resolveChatChannelContext,
        ResolveChatRequestedThreadId $resolveChatRequestedThreadId,
    ): JsonResponse {
        $channelUuid = $request->validated('channel');
        $threadUuid = $request->validated('thread');

        [$channel, $serviceRequest] = $resolveChatChannelContext($channelUuid, $request->user());

        Gate::authorize('view', $channel);
        Gate::authorize('create', Message::class);

        $decision = $orchestrator->resolve(
            channel: $channel,
            serviceRequest: $serviceRequest,
            actor: $request->user(),
            thread: $resolveChatRequestedThreadId($threadUuid, $channel, $serviceRequest),
            message: $request->validated('content'),
        );
        $thread = $decision->thread;

        $activePresenters = $this->resolveActivePresenters($thread);

        if ($activePresenters->isNotEmpty()) {
            $content = $request->validated('content');

            if (! is_string($content) || trim($content) === '') {
                abort(422, 'A text message is required for agent prompts.');
            }
        }

        if ($activePresenters->isEmpty()) {
            return $this->storeHumanMessage($request, $channel, $serviceRequest, $thread);
        }

        return $this->promptAgentThread($request, $channel, $serviceRequest, $thread, $request->user(), $activePresenters);
    }

    /**
     * @return Collection<int, ThreadActor>
     */
    protected function resolveActivePresenters(Thread $thread): Collection
    {
        return $thread->presenterActors()->get();
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
                'source' => 'peer_message',
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
        ]);
    }

    protected function promptAgentThread(
        StoreChatRequest $request,
        Channel $channel,
        ?ServiceRequest $serviceRequest,
        Thread $thread,
        User $actor,
        Collection $activePresenters,
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

        $existingUserMessage = $this->findExistingUserMessage($thread, $actor, $idempotencyKey);

        if ($existingUserMessage) {
            if ($existingUserMessage->body !== $content) {
                $existingUserMessage->forceFill([
                    'body' => $content,
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
                'text' => $firstAssistantMessage?->body,
                'message_id' => $existingUserMessage->id,
                'assistant_message_id' => $firstAssistantMessage?->id,
                'assistant_messages' => $existingAssistantMessages
                    ->map(fn (Message $message): array => [
                        'id' => $message->id,
                        'actor_key' => data_get($message->meta, 'actor_key'),
                        'text' => $message->body,
                        'created_at' => optional($message->created_at)?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                'duplicate' => true,
                'pending' => $pendingReplies,
                'pending_presenters' => max($expectedPresenterReplyCount - $existingAssistantMessages->count(), 0),
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

        $activePresenters->each(function (ThreadActor $presenter) use ($thread, $userMessage, $actor): void {
            GenerateAgentReply::dispatch(
                threadId: $thread->id,
                userMessageId: $userMessage->id,
                actorId: $actor->id,
                primaryPresenterActorId: $presenter->id,
            )->afterCommit();
        });

        return response()->json([
            'message' => 'Agent response queued.',
            'thread' => $thread->uuid,
            'channel' => $channel->uuid,
            'message_id' => $userMessage->id,
            'assistant_message_id' => null,
            'pending_presenters' => $this->expectedPresenterReplyCount($activePresenters),
            'pending' => true,
        ], 202);
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
}
