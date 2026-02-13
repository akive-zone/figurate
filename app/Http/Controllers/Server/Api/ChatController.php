<?php

namespace App\Http\Controllers\Server\Api;

use App\Ai\Agents\OrderAgent;
use App\Ai\Agents\RequestAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\StoreChatRequest;
use App\Jobs\ProcessThreadObservers;
use App\Models\Server\Channel;
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
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Agent;

class ChatController extends Controller
{
    public function store(
        StoreChatRequest $request,
        Channel $channel,
        ConversationOrchestrator $orchestrator
    ): JsonResponse {
        Gate::authorize('view', $channel);
        Gate::authorize('create', Message::class);

        $serviceRequest = $channel->requests()->first();

        if (! $serviceRequest) {
            abort(404);
        }

        $decision = $orchestrator->resolve(
            channel: $channel,
            serviceRequest: $serviceRequest,
            actor: $request->user(),
            requestedThreadId: $request->validated('thread_id'),
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
        ServiceRequest $serviceRequest,
        Thread $thread,
        array $orchestrationActions = []
    ): JsonResponse {
        if (! $serviceRequest->hasParticipant($request->user())) {
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

        $channel->forceFill([
            'last_message_at' => now(),
        ])->save();

        ProcessThreadObservers::dispatch($thread->id, $message->id);

        return response()->json([
            'message' => 'Message sent.',
            'thread_id' => $thread->id,
            'message_id' => $message->id,
            'observer_status' => 'queued',
            'mode' => 'human_chat',
            'orchestration_actions' => $orchestrationActions,
        ]);
    }

    protected function promptAgentThread(
        StoreChatRequest $request,
        Channel $channel,
        ServiceRequest $serviceRequest,
        Thread $thread,
        User $actor,
        array $orchestrationActions = []
    ): JsonResponse {
        if (! $serviceRequest->hasParticipant($actor)) {
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

        $channel->forceFill([
            'last_message_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Agent responded.',
            'thread_id' => $thread->id,
            'channel_id' => $channel->id,
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
}
