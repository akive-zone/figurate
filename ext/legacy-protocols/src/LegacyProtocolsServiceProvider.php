<?php

namespace Figurate\LegacyProtocols;

use App\Features\Actions\Chat\ProtocolRegistry;
use Figurate\LegacyProtocols\Conversation\ActivityPubOutboundMessageSender;
use Figurate\LegacyProtocols\Conversation\NostrOutboundMessageSender;
use Figurate\LegacyProtocols\Conversation\Protocols\ActivityPubProtocol;
use Figurate\LegacyProtocols\Conversation\Protocols\NostrProtocol;
use Illuminate\Support\ServiceProvider;

class LegacyProtocolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActivityPubOutboundMessageSender::class);
        $this->app->singleton(NostrOutboundMessageSender::class);
        $this->app->singleton(ActivityPubProtocol::class);
        $this->app->singleton(NostrProtocol::class);
        $this->app->tag([
            ActivityPubProtocol::class,
            NostrProtocol::class,
        ], ProtocolRegistry::DriverTag);
    }
}
