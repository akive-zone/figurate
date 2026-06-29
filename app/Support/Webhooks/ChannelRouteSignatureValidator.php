<?php

namespace App\Support\Webhooks;

use App\Models\Server\Channel;
use App\Support\Channels\ChannelRouteWebhookLocator;
use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class ChannelRouteSignatureValidator implements SignatureValidator
{
    public function __construct(
        protected ChannelRouteWebhookLocator $channelRouteWebhookLocator,
    ) {}

    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $route = $this->channelRouteWebhookLocator->fromRequest($request);

        if ($route === null) {
            return false;
        }

        $channel = $route->channel;

        if (! $channel instanceof Channel) {
            return false;
        }

        if (! in_array($channel->status, [Channel::StatusActive, null], true)) {
            return false;
        }

        if (! in_array($route->status, [Channel::StatusActive, null], true)) {
            return false;
        }

        $inboundAuth = is_array(data_get($route->config, 'inbound.auth'))
            ? data_get($route->config, 'inbound.auth')
            : [];
        $type = $this->normalizedString($inboundAuth['type'] ?? null) ?? 'none';

        if ($type === 'none') {
            return true;
        }

        $expected = $this->normalizedString($inboundAuth['secret'] ?? null)
            ?? $this->normalizedString($inboundAuth['token'] ?? null)
            ?? $this->normalizedString($inboundAuth['value'] ?? null);

        if ($expected === null) {
            return false;
        }

        if ($type === 'bearer') {
            $providedBearer = $this->normalizedString($request->bearerToken());

            return $providedBearer !== null && hash_equals($expected, $providedBearer);
        }

        $header = $this->normalizedString($inboundAuth['header'] ?? null)
            ?? $this->normalizedString($config->signatureHeaderName)
            ?? 'X-Channel-Key';
        $provided = $this->normalizedString($request->header($header));

        return $provided !== null && hash_equals($expected, $provided);
    }

    protected function normalizedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
