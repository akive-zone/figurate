<?php

namespace App\Ai\Middleware\Workflows;

use App\Ai\Storage\ThreadConversationStore;
use Closure;
use Illuminate\Contracts\Container\Container;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Prompts\AgentPrompt;

class UseThreadConversationStore
{
    public function __construct(protected ?Container $container = null)
    {
        $this->container ??= app();
    }

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $abstract = ConversationStore::class;
        $hadBinding = $this->container->bound($abstract);
        $bindings = method_exists($this->container, 'getBindings')
            ? $this->container->getBindings()
            : [];
        $originalBinding = $bindings[$abstract] ?? null;
        $hadResolvedInstance = $this->container->resolved($abstract);
        $originalInstance = $hadResolvedInstance ? $this->container->make($abstract) : null;

        $this->container->forgetInstance($abstract);
        $this->container->singleton($abstract, ThreadConversationStore::class);

        try {
            return $next($prompt);
        } finally {
            $this->container->forgetInstance($abstract);

            if ($hadBinding && is_array($originalBinding)) {
                $this->container->bind(
                    $abstract,
                    $originalBinding['concrete'],
                    (bool) ($originalBinding['shared'] ?? false),
                );
            }

            if ($hadResolvedInstance && $originalInstance !== null) {
                $this->container->instance($abstract, $originalInstance);
            }
        }
    }
}
