<?php

namespace App\Ai\Middleware\Workflows;

use App\Ai\Support\FulfillmentContext;
use App\Ai\Support\ThreadContextResolver;
use App\Models\Server\Thread;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InitializeFulfillmentContext
{
    public function __construct(
        protected FulfillmentContext $fulfillmentContext = new FulfillmentContext,
        protected ThreadContextResolver $threadContextResolver = new ThreadContextResolver,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $thread = $this->resolveThread($prompt);

        if (! $thread) {
            return $next($prompt);
        }

        $requestPost = $this->fulfillmentContext->resolveSubjectFromThread($thread);
        $channel = $this->threadContextResolver->resolveChannel($thread);

        $presenterCount = $thread->presenterActors()->count();
        $observerCount = $thread->observerActors()->count();

        $context = implode("\n", [
            'Fulfillment context bootstrap (system policy):',
            "- Thread id: {$thread->id}",
            "- Thread uuid: {$thread->uuid}",
            "- Thread purpose: {$thread->purpose}",
            "- Thread phase: {$thread->phase}",
            '- Channel id: '.($channel?->id ?? 'none'),
            '- Request id: '.($requestPost?->id ?? 'none'),
            '- Request status: '.($requestPost?->status ?? 'none'),
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
