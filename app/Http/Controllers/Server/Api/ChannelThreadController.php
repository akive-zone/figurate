<?php

namespace App\Http\Controllers\Server\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\ActivateChannelThreadRequest;
use App\Http\Requests\Signal\StoreChannelThreadRequest;
use App\Http\Requests\Signal\UpdateThreadRequest;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ChannelThreadController extends Controller
{
    public function store(StoreChannelThreadRequest $request, Channel $channel): JsonResponse
    {
        Gate::authorize('view', $channel);
        Gate::authorize('create', Thread::class);

        $serviceRequest = $channel->requests()->first();

        if (! $serviceRequest || ! $serviceRequest->hasParticipant($request->user())) {
            abort(403);
        }

        $payload = $request->validated();

        if (
            $payload['purpose'] === Thread::PurposeMain &&
            $serviceRequest->threads()->where('purpose', Thread::PurposeMain)->where('status', 'open')->exists()
        ) {
            abort(422, 'Main thread already exists for this channel.');
        }

        $thread = $serviceRequest->threads()->create([
            'purpose' => $payload['purpose'],
            'title' => $payload['title'] ?? $this->defaultTitle($payload['purpose']),
            'phase' => $payload['phase'],
            'status' => 'open',
        ]);

        $thread->actors()->create([
            'actorable_type' => $payload['handler_actor'] ?? $this->defaultHandlerActor($payload['purpose']),
            'actorable_id' => null,
            'role' => ThreadActor::RoleHandler,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        $this->activateForActor($channel, $thread, $request->user());

        $thread->events()->create([
            'message_id' => null,
            'actor_key' => 'orchestrator',
            'event_type' => 'orchestration.thread_created',
            'severity' => 'low',
            'payload' => [
                'purpose' => $thread->purpose,
                'phase' => $thread->phase,
                'source' => 'api.thread.store',
            ],
        ]);

        return response()->json([
            'message' => 'Thread created.',
            'thread_id' => $thread->id,
            'purpose' => $thread->purpose,
            'phase' => $thread->phase,
        ], 201);
    }

    public function activate(ActivateChannelThreadRequest $request, Channel $channel, Thread $thread): JsonResponse
    {
        Gate::authorize('view', $channel);
        Gate::authorize('view', $thread);

        $serviceRequest = $channel->requests()->first();

        if (! $this->belongsToRequest($thread, $serviceRequest)) {
            abort(404);
        }

        $this->activateForActor($channel, $thread, $request->user());

        $thread->events()->create([
            'message_id' => null,
            'actor_key' => 'orchestrator',
            'event_type' => 'orchestration.thread_activated',
            'severity' => 'low',
            'payload' => [
                'source' => 'api.thread.activate',
            ],
        ]);

        return response()->json([
            'message' => 'Thread activated.',
            'thread_id' => $thread->id,
        ]);
    }

    public function update(UpdateThreadRequest $request, Thread $thread): JsonResponse
    {
        Gate::authorize('update', $thread);

        $payload = $request->validated();

        if (($payload['status'] ?? null) === 'closed' && $thread->purpose === Thread::PurposeMain) {
            abort(422, 'Main thread cannot be closed while channel is open.');
        }

        $thread->forceFill([
            'phase' => $payload['phase'] ?? $thread->phase,
            'status' => $payload['status'] ?? $thread->status,
        ])->save();

        $thread->events()->create([
            'message_id' => null,
            'actor_key' => 'orchestrator',
            'event_type' => 'orchestration.thread_updated',
            'severity' => 'low',
            'payload' => [
                'phase' => $thread->phase,
                'status' => $thread->status,
                'source' => 'api.thread.update',
            ],
        ]);

        return response()->json([
            'message' => 'Thread updated.',
            'thread_id' => $thread->id,
            'phase' => $thread->phase,
            'status' => $thread->status,
        ]);
    }

    protected function activateForActor(Channel $channel, Thread $thread, User $actor): void
    {
        ChannelActorState::query()->updateOrCreate(
            [
                'channel_id' => $channel->id,
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => $actor->getKey(),
            ],
            [
                'thread_id' => $thread->id,
                'status' => ChannelActorState::StatusActive,
            ],
        );
    }

    protected function belongsToRequest(Thread $thread, ?ServiceRequest $serviceRequest): bool
    {
        if (! $serviceRequest) {
            return false;
        }

        return $thread->threadable_type === $serviceRequest->getMorphClass()
            && (int) $thread->threadable_id === (int) $serviceRequest->getKey();
    }

    protected function defaultHandlerActor(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposeExecution, Thread::PurposeBilling => ThreadActor::ActorOrderAgent,
            Thread::PurposeSupport => ThreadActor::ActorHumanChat,
            default => ThreadActor::ActorRequestAgent,
        };
    }

    protected function defaultTitle(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposePlanning => 'Planning',
            Thread::PurposeExecution => 'Execution',
            Thread::PurposeBilling => 'Billing',
            Thread::PurposeDispute => 'Dispute',
            Thread::PurposeSupport => 'Support',
            Thread::PurposeSystem => 'System',
            default => 'Project Main',
        };
    }
}
