<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Thread;
use App\Models\Server\ThreadEvent;

class RecordConversationOperationEvents
{
    /**
     * @param  list<array<string, mixed>>  $actions
     */
    public function execute(Thread $thread, array $actions): void
    {
        foreach ($actions as $action) {
            $eventType = (string) $action['event_type'];

            $thread->events()->create([
                'thread_actor_id' => null,
                'post_id' => null,
                'event_key' => 'orchestrator',
                'layer' => ThreadEvent::LayerExecution,
                'kind' => ThreadEvent::KindOrchestration,
                'operation' => str_replace('orchestration.', '', $eventType),
                'state' => ThreadEvent::StateCompleted,
                'event_type' => $eventType,
                'severity' => 'low',
                'payload' => $action,
            ]);
        }
    }
}
