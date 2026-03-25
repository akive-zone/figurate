<?php

namespace App\Listeners\Server\Ai;

use App\Ai\Agents\ObserverAgent;
use App\Models\Server\ThreadEvent;
use Laravel\Ai\Events\PromptingAgent;

class RecordObserverAgentPrompting
{
    public function handle(PromptingAgent $event): void
    {
        $agent = $event->prompt->agent;

        if (! $agent instanceof ObserverAgent) {
            return;
        }

        $thread = $agent->thread;
        $message = $agent->message;
        $threadActor = $agent->threadActor;

        $thread->events()->create([
            'thread_actor_id' => $threadActor?->id,
            'post_id' => $message->id,
            'event_key' => $threadActor?->actorReference() ?? 'observer_agent',
            'layer' => ThreadEvent::LayerExecution,
            'kind' => ThreadEvent::KindObserver,
            'operation' => 'observer.prompt',
            'state' => ThreadEvent::StateRequested,
            'event_type' => 'observer_prompting',
            'severity' => 'low',
            'payload' => [
                'invocation_id' => $event->invocationId,
                'provider' => $event->prompt->provider::class,
                'model' => $event->prompt->model,
            ],
        ]);
    }
}
