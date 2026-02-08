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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Agent;

class ChatController extends Controller
{
    public function store(StoreChatRequest $request, Channel $channel): JsonResponse
    {
        Gate::authorize('view', $channel);
        Gate::authorize('create', Message::class);

        $serviceRequest = $channel->requests()->first();

        if (! $serviceRequest) {
            abort(404);
        }

        $thread = $this->resolveThread(
            serviceRequest: $serviceRequest,
            threadId: $request->validated('thread_id'),
        );

        $primaryHandler = $this->resolvePrimaryHandlerActor($thread);

        return match ($primaryHandler->actorName()) {
            ThreadActor::ActorHumanChat => $this->storeHumanMessage($request, $channel, $serviceRequest, $thread),
            default => $this->promptAgentThread($request, $channel, $serviceRequest, $thread),
        };
    }

    protected function resolveThread(ServiceRequest $serviceRequest, mixed $threadId): Thread
    {
        $query = $serviceRequest->threads()->where('status', 'open');

        if ($threadId !== null) {
            $thread = $query->whereKey((int) $threadId)->first();

            if (! $thread) {
                abort(404);
            }

            return $thread;
        }

        $thread = $query->latest('id')->first();

        if (! $thread) {
            abort(422, 'No active thread exists for this channel.');
        }

        return $thread;
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
        Thread $thread
    ): JsonResponse {
        if (! $serviceRequest->hasParticipant($request->user())) {
            abort(403);
        }

        $message = $thread->messages()->create([
            'sender_id' => $request->user()->id,
            'type' => 'text',
            'body' => $request->validated('content'),
            'attachments' => null,
            'meta' => null,
        ]);

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
        ]);
    }

    protected function promptAgentThread(
        StoreChatRequest $request,
        Channel $channel,
        ServiceRequest $serviceRequest,
        Thread $thread
    ): JsonResponse {
        if (! $serviceRequest->hasUserActor($request->user(), ServiceRequest::ActionAsker)) {
            abort(403);
        }

        $primaryHandler = $this->resolvePrimaryHandlerActor($thread);
        $agent = $this->resolveAgent($primaryHandler);
        $memory = $this->resolveMemory($thread, $primaryHandler);

        if ($memory->conversation_id) {
            $agent->continue($memory->conversation_id, $request->user());
        } else {
            $agent->forUser($request->user());
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

    protected function resolveAgent(ThreadActor $primaryHandler): Agent
    {
        $thread = $primaryHandler->thread;

        return match ($primaryHandler->actorName()) {
            ThreadActor::ActorOrderAgent => OrderAgent::make(thread: $thread),
            default => RequestAgent::make(thread: $thread),
        };
    }
}
