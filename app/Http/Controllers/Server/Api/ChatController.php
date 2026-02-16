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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Agent;

class ChatController extends Controller
{
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
            ThreadActor::ActorHumanChat => $this->storeHumanMessage($request, $channel, $serviceRequest, $thread, $decision->actions),
            default => $this->promptAgentThread($request, $channel, $serviceRequest, $thread, $request->user(), $decision->actions),
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
        Thread $thread,
        array $orchestrationActions = []
    ): JsonResponse {
        if (! $this->canActorWrite($channel, $serviceRequest, $request->user())) {
            abort(403);
        }

        /** @var Collection<int, UploadedFile> $uploadedMedia */
        $uploadedMedia = collect($request->file('contents', []))
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->values();

        $content = $request->validated('content');

        $message = $thread->messages()->create([
            'senderable_type' => $request->user()->getMorphClass(),
            'senderable_id' => $request->user()->getKey(),
            'type' => 'text',
            'body' => is_string($content) && $content !== '' ? $content : null,
            'attachments' => null,
            'meta' => null,
        ]);

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
            'orchestration_actions' => $orchestrationActions,
        ]);
    }

    protected function promptAgentThread(
        StoreChatRequest $request,
        Channel $channel,
        ?ServiceRequest $serviceRequest,
        Thread $thread,
        User $actor,
        array $orchestrationActions = []
    ): JsonResponse {
        if (! $this->canActorWrite($channel, $serviceRequest, $actor)) {
            abort(403);
        }

        $primaryHandler = $this->resolvePrimaryHandlerActor($thread);
        $agent = $this->resolveAgent($primaryHandler, $actor);
        $memory = $this->resolveMemory($thread, $primaryHandler);

        if ($memory->conversation_id) {
            $agent->continue($memory->conversation_id, $actor);
        } else {
            $agent->forUser($actor);
        }

        $response = $agent->prompt($request->validated('content'));

        if ($response->conversationId) {
            $memory->forceFill([
                'conversation_id' => $response->conversationId,
                'last_used_at' => now(),
            ])->save();
        }

        return response()->json([
            'message' => 'Agent responded.',
            'thread' => $thread->uuid,
            'channel' => $channel->uuid,
            'conversation_id' => $response->conversationId ?? $memory->conversation_id,
            'text' => $response->text,
            'mode' => 'agent',
            'orchestration_actions' => $orchestrationActions,
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
}
