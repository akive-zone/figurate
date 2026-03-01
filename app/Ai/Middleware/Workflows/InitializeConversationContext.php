<?php

namespace App\Ai\Middleware\Workflows;

use App\Ai\Support\ThreadContextResolver;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InitializeConversationContext
{
    public function __construct(
        protected ThreadContextResolver $threadContextResolver = new ThreadContextResolver,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $thread = $this->resolveThread($prompt);

        if (! $thread) {
            return $next($prompt);
        }

        $channel = $this->threadContextResolver->resolveChannel($thread);

        $presenterCount = $thread->actors()
            ->where('role', ThreadActor::RolePresenter)
            ->where('status', ThreadActor::StatusActive)
            ->count();
        $observerCount = $thread->actors()
            ->where('role', ThreadActor::RoleObserver)
            ->where('status', ThreadActor::StatusActive)
            ->count();

        $context = implode("\n", [
            'Conversation context bootstrap (system policy):',
            "- Thread id: {$thread->id}",
            "- Thread uuid: {$thread->uuid}",
            "- Thread purpose: {$thread->purpose}",
            "- Thread phase: {$thread->phase}",
            '- Channel id: '.($channel?->id ?? 'none'),
            "- Presenter actors: {$presenterCount}",
            "- Observer actors: {$observerCount}",
            '- Keep all decisions consistent with this thread context.',
        ]);

        return $next($prompt->prepend($context));
    }

    protected function resolveThread(AgentPrompt $prompt): ?Thread
    {
        $agent = $prompt->agent;

        if (! property_exists($agent, 'thread')) {
            return null;
        }

        $thread = $agent->thread;

        return $thread instanceof Thread ? $thread : null;
    }
}
