<?php

namespace App\Providers\Server;

use App\Features\Actions\Chat\ActivityPubOutboundMessageSender;
use App\Features\Actions\Chat\ChatProtocolRegistry;
use App\Features\Actions\Chat\NostrOutboundMessageSender;
use App\Features\Actions\Chat\Protocols\ActivityPubChatProtocol;
use App\Features\Actions\Chat\Protocols\NostrChatProtocol;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Policies\Server\ChannelPolicy;
use App\Policies\Server\MessagePolicy;
use App\Policies\Server\ThreadPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ChatProtocolRegistry::class);
        $this->app->singleton(ActivityPubOutboundMessageSender::class);
        $this->app->singleton(NostrOutboundMessageSender::class);
        $this->app->singleton(ActivityPubChatProtocol::class);
        $this->app->singleton(NostrChatProtocol::class);
        $this->app->tag([
            ActivityPubChatProtocol::class,
            NostrChatProtocol::class,
        ], ChatProtocolRegistry::DriverTag);
    }

    public function boot(ChatProtocolRegistry $chatProtocolRegistry): void
    {
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Thread::class, ThreadPolicy::class);

        $existingWebhookConfigs = collect(config('webhook-client.configs', []))
            ->filter(fn (mixed $config): bool => is_array($config) && is_string($config['name'] ?? null))
            ->keyBy(fn (array $config): string => $config['name']);

        $protocolWebhookConfigs = collect($chatProtocolRegistry->webhookConfigs())
            ->keyBy(fn (array $config): string => $config['name']);

        config([
            'webhook-client.configs' => $existingWebhookConfigs
                ->merge($protocolWebhookConfigs)
                ->values()
                ->all(),
        ]);

        $chatProtocolRegistry->registerRoutes();
    }
}
