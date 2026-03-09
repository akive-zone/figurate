<?php

namespace App\Listeners\Server\Ai;

use App\Ai\Agents\ObserverAgent;
use App\Models\Server\ThreadEvent;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Responses\AgentResponse;

class RecordObserverAgentPrompted
{
    public function handle(AgentPrompted $event): void
    {
        $agent = $event->prompt->agent;

        if (! $agent instanceof ObserverAgent) {
            return;
        }

        $thread = $agent->thread;
        $message = $agent->message;
        $threadActor = $agent->threadActor;
        $response = $event->response;

        $thread->events()->create([
            'thread_actor_id' => $threadActor?->id,
            'message_id' => $message->id,
            'event_key' => $threadActor?->actorReference() ?? 'observer_agent',
            'layer' => ThreadEvent::LayerExecution,
            'kind' => ThreadEvent::KindObserver,
            'operation' => 'observer.prompt',
            'state' => ThreadEvent::StateCompleted,
            'event_type' => 'observer_prompted',
            'severity' => 'low',
            'payload' => [
                'invocation_id' => $event->invocationId,
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
                'usage' => $response->usage->toArray(),
                'conversation_id' => $response instanceof AgentResponse ? $response->conversationId : null,
            ],
        ]);
    }
}
