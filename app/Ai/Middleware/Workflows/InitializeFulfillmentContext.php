<?php

namespace App\Ai\Middleware\Workflows;

use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InitializeFulfillmentContext
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $thread = $this->resolveThread($prompt);

        if (! $thread) {
            return $next($prompt);
        }

        $threadable = $thread->threadable;
        $request = $threadable instanceof ServiceRequest ? $threadable : null;
        $channel = $threadable instanceof Channel
            ? $threadable
            : $request?->channels()->latest('channels.id')->first();

        $presenterCount = $thread->presenterActors()->count();
        $observerCount = $thread->observerActors()->count();

        $context = implode("\n", [
            'Fulfillment context bootstrap (system policy):',
            "- Thread id: {$thread->id}",
            "- Thread uuid: {$thread->uuid}",
            "- Thread purpose: {$thread->purpose}",
            "- Thread phase: {$thread->phase}",
            '- Channel id: '.($channel?->id ?? 'none'),
            '- Request id: '.($request?->id ?? 'none'),
            '- Request status: '.($request?->status ?? 'none'),
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
