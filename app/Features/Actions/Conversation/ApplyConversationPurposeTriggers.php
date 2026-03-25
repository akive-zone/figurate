<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Channel;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use Illuminate\Database\Eloquent\Builder;

class ApplyConversationPurposeTriggers
{
    /**
     * @return array{0: Thread, 1: list<array<string, mixed>>}
     */
    public function execute(Channel $channel, Thread $thread, ?string $message): array
    {
        $targetPurpose = $this->detectPurposeFromMessage($message);

        if (! $targetPurpose || $targetPurpose === $thread->purpose) {
            return [$thread, []];
        }

        $openThread = $this->threadsQuery($channel)
            ->where('status', 'open')
            ->where('purpose', $targetPurpose)
            ->latest('id')
            ->first();

        if ($openThread) {
            return [$openThread, [[
                'event_type' => 'orchestration.thread_switched',
                'trigger' => 'message_intent',
                'to_thread_id' => $openThread->id,
                'purpose' => $targetPurpose,
            ]]];
        }

        $spawnedThread = $this->createPurposeThread($channel, $targetPurpose);

        return [$spawnedThread, [[
            'event_type' => 'orchestration.thread_spawned',
            'trigger' => 'message_intent',
            'to_thread_id' => $spawnedThread->id,
            'purpose' => $targetPurpose,
        ]]];
    }

    protected function threadsQuery(Channel $channel): Builder
    {
        return Thread::query()->whereIn('id', $channel->conversationThreadIds()->all());
    }

    protected function createPurposeThread(Channel $channel, string $purpose): Thread
    {
        $thread = $channel->threads()->create([
            'purpose' => $purpose,
            'title' => $this->defaultTitle($purpose),
            'phase' => $this->defaultPhase($purpose),
            'status' => 'open',
        ]);

        $thread->actors()->create([
            'actorable_type' => $this->defaultHandlerActor($purpose),
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        return $thread;
    }

    protected function detectPurposeFromMessage(?string $message): ?string
    {
        $content = mb_strtolower(trim((string) $message));

        if ($content === '') {
            return null;
        }

        if (preg_match('/\b(dispute|refund|complaint|fraud|scam|chargeback)\b/u', $content) === 1) {
            return Thread::PurposeDispute;
        }

        if (preg_match('/\b(payment|invoice|billing|bill|cost|price)\b/u', $content) === 1) {
            return Thread::PurposeBilling;
        }

        if (preg_match('/\b(start work|begin work|execution|deliver|in progress|kickoff)\b/u', $content) === 1) {
            return Thread::PurposeExecution;
        }

        if (preg_match('/\b(plan|planning|scope|breakdown|steps)\b/u', $content) === 1) {
            return Thread::PurposePlanning;
        }

        return null;
    }

    protected function defaultHandlerActor(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposeExecution, Thread::PurposeBilling => ThreadActor::ActorOrderAgent,
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

    protected function defaultPhase(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposePlanning => 'planning',
            Thread::PurposeExecution => 'execution',
            Thread::PurposeBilling => 'billing',
            Thread::PurposeDispute => 'dispute',
            Thread::PurposeSupport => 'support',
            Thread::PurposeSystem => 'system',
            default => 'request_intake',
        };
    }
}
