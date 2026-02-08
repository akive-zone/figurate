<?php

namespace App\Http\Controllers\Server\Api;

use App\Ai\Agents\OrderAgent;
use App\Ai\Agents\RequestAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\StoreAgentPromptRequest;
use App\Http\Requests\Signal\StoreAgentThreadRequest;
use App\Http\Requests\Signal\StoreThreadMessageRequest;
use App\Jobs\ProcessThreadObservers;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadObserver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Agent;

class ChatController extends Controller
{
    public function storeThread(StoreAgentThreadRequest $request, Channel $channel): JsonResponse
    {
        Gate::authorize('view', $channel);

        $serviceRequest = $channel->requests()->first();

        if (! $serviceRequest || ! $serviceRequest->hasUserActor($request->user(), ServiceRequest::ActionAsker)) {
            abort(403);
        }

        $thread = $serviceRequest->threads()->create([
            'created_by' => $request->user()->id,
            'title' => $request->validated('title'),
            'phase' => $request->validated('phase'),
            'agent_key' => $request->validated('agent_key'),
            'status' => 'open',
        ]);

        if ($thread->agent_key === Thread::AgentHumanChat) {
            $thread->observers()->create([
                'observer_key' => ThreadObserver::SafetyGuard,
                'mode' => ThreadObserver::ModeEnforcing,
                'status' => 'active',
                'config' => null,
            ]);
        }

        return response()->json([
            'message' => 'New thread started.',
            'thread_id' => $thread->id,
        ]);
    }

    public function storeMessage(
        StoreThreadMessageRequest $request,
        Channel $channel,
        Thread $thread
    ): JsonResponse {
        Gate::authorize('view', $channel);
        Gate::authorize('create', Message::class);

        $serviceRequest = $channel->requests()->first();

        if (
            ! $serviceRequest ||
            $thread->threadable_type !== ServiceRequest::class ||
            $thread->threadable_id !== $serviceRequest->id
        ) {
            abort(404);
        }

        if (! $serviceRequest->hasParticipant($request->user())) {
            abort(403);
        }

        if ($thread->agent_key !== Thread::AgentHumanChat) {
            abort(422, 'Direct chat messages are only supported on human_chat threads.');
        }

        $message = $thread->messages()->create([
            'sender_id' => $request->user()->id,
            'type' => 'text',
            'body' => $request->validated('message'),
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
        ]);
    }

    public function promptThread(
        StoreAgentPromptRequest $request,
        Channel $channel,
        Thread $thread
    ): JsonResponse {
        Gate::authorize('view', $channel);

        $serviceRequest = $channel->requests()->first();

        if (
            ! $serviceRequest ||
            $thread->threadable_type !== ServiceRequest::class ||
            $thread->threadable_id !== $serviceRequest->id
        ) {
            abort(404);
        }

        if (! $serviceRequest->hasUserActor($request->user(), ServiceRequest::ActionAsker)) {
            abort(403);
        }

        $agent = $this->resolveAgent($thread);

        if ($thread->ai_conversation_id) {
            $agent->continue($thread->ai_conversation_id, $request->user());
        } else {
            $agent->forUser($request->user());
        }

        $response = $agent->prompt($request->validated('message'));

        if (! $thread->ai_conversation_id && $response->conversationId) {
            $thread->forceFill([
                'ai_conversation_id' => $response->conversationId,
            ])->save();
        }

        $channel->forceFill([
            'last_message_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Agent responded.',
            'thread_id' => $thread->id,
            'channel_id' => $channel->id,
            'ai_conversation_id' => $response->conversationId ?? $thread->ai_conversation_id,
            'text' => $response->text,
        ]);
    }

    protected function resolveAgent(Thread $thread): Agent
    {
        return match ($thread->agent_key) {
            Thread::AgentOrder => OrderAgent::make(thread: $thread),
            default => RequestAgent::make(thread: $thread),
        };
    }
}
