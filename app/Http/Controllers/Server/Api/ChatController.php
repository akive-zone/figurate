<?php

namespace App\Http\Controllers\Server\Api;

use App\Ai\Agents\OrderAgent;
use App\Ai\Agents\RequestAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\StoreAgentPromptRequest;
use App\Http\Requests\Signal\StoreAgentThreadRequest;
use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Agent;

class ChatController extends Controller
{
    public function storeThread(StoreAgentThreadRequest $request, Channel $channel): JsonResponse
    {
        Gate::authorize('view', $channel);

        $serviceRequest = $channel->requests()->first();

        if (! $serviceRequest || $serviceRequest->requester_id !== $request->user()->id) {
            abort(403);
        }

        $thread = $serviceRequest->threads()->create([
            'created_by' => $request->user()->id,
            'title' => $request->validated('title'),
            'phase' => $request->validated('phase'),
            'agent_key' => $request->validated('agent_key'),
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'New thread started.',
            'thread_id' => $thread->id,
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

        if ($serviceRequest->requester_id !== $request->user()->id) {
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
