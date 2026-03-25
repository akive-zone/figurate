<?php

namespace App\Providers;

use App\Features\Actions\Conversation\AgentPromptOutboundMessageSender;
use App\Features\Actions\Conversation\ChannelOutboundMessageSender;
use App\Features\Actions\Conversation\ProtocolRegistry;
use App\Features\Actions\Conversation\Protocols\AgentPromptProtocol;
use App\Features\Actions\Conversation\Protocols\ChannelProtocol;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Policies\Server\MessagePolicy;
use App\Policies\Server\SpacePolicy;
use App\Policies\Server\ThreadPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ComposeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ProtocolRegistry::class);
        $this->app->singleton(AgentPromptOutboundMessageSender::class);
        $this->app->singleton(ChannelOutboundMessageSender::class);
        $this->app->singleton(AgentPromptProtocol::class);
        $this->app->singleton(ChannelProtocol::class);
        $this->app->tag([
            AgentPromptProtocol::class,
            ChannelProtocol::class,
        ], ProtocolRegistry::DriverTag);
    }

    public function boot(ProtocolRegistry $protocolRegistry): void
    {
        Gate::policy(Space::class, SpacePolicy::class);
        Gate::policy(Post::class, MessagePolicy::class);
        Gate::policy(Thread::class, ThreadPolicy::class);

        $existingWebhookConfigs = collect(config('webhook-client.configs', []))
            ->filter(fn (mixed $config): bool => is_array($config) && is_string($config['name'] ?? null))
            ->keyBy(fn (array $config): string => $config['name']);

        $protocolWebhookConfigs = collect($protocolRegistry->webhookConfigs())
            ->keyBy(fn (array $config): string => $config['name']);

        config([
            'webhook-client.configs' => $existingWebhookConfigs
                ->merge($protocolWebhookConfigs)
                ->values()
                ->all(),
        ]);

        $protocolRegistry->registerRoutes();
    }
}
