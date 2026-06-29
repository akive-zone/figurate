<?php

namespace App\Jobs;

use App\Support\Channels\ChannelRouteIngress;
use App\Support\Channels\ChannelRouteWebhookLocator;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessInboundChannelRouteWebhookJob extends ProcessWebhookJob
{
    public function handle(
        ChannelRouteWebhookLocator $channelRouteWebhookLocator,
        ChannelRouteIngress $channelRouteIngress,
    ): void {
        $route = $channelRouteWebhookLocator->fromWebhookCall($this->webhookCall);

        if ($route === null) {
            return;
        }

        $payload = is_array($this->webhookCall->payload ?? null) ? $this->webhookCall->payload : [];
        $headers = is_array($this->webhookCall->headers ?? null) ? $this->webhookCall->headers : [];

        $channelRouteIngress->receiveStored($route, $payload, $headers);
    }
}
