<?php

namespace App\Support\Channels;

use App\Models\Server\ChannelRoute;
use Illuminate\Http\Request;
use Spatie\WebhookClient\Models\WebhookCall;

class ChannelRouteWebhookLocator
{
    public function fromRequest(Request $request): ?ChannelRoute
    {
        return $this->fromRouteIdentifier($request->route('route'));
    }

    public function fromWebhookCall(WebhookCall $webhookCall): ?ChannelRoute
    {
        $path = parse_url((string) $webhookCall->url, PHP_URL_PATH);

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (! preg_match('#/channel-routes/(\d+)/inbound/?$#', $path, $matches)) {
            return null;
        }

        return $this->findRoute((int) $matches[1]);
    }

    protected function fromRouteIdentifier(mixed $value): ?ChannelRoute
    {
        if ($value instanceof ChannelRoute) {
            return $value->loadMissing('channel');
        }

        if (is_int($value)) {
            return $this->findRoute($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        return $this->findRoute((int) $trimmed);
    }

    protected function findRoute(int $routeId): ?ChannelRoute
    {
        if ($routeId < 1) {
            return null;
        }

        return ChannelRoute::query()
            ->with('channel')
            ->find($routeId);
    }
}
