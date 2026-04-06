<?php

namespace App\Support\Channels\Drivers;

use App\Ai\Support\Mcp\McpRemoteEndpointClient;
use App\Models\Server\Channel;

class McpChannelDriver extends GenericChannelDriver
{
    public function __construct(
        protected McpRemoteEndpointClient $remoteEndpointClient,
    ) {}

    public function key(): string
    {
        return Channel::ProtocolMcp;
    }

    public function supportedTransports(): array
    {
        return [Channel::TransportHttp, Channel::TransportWebsocket, Channel::TransportStdio];
    }

    public function supportedProtocols(): array
    {
        return [Channel::ProtocolMcp];
    }

    public function capabilities(?Channel $channel = null): array
    {
        return ['context.read', 'tool.call'];
    }

    public function prepareForRegistration(array $attributes): array
    {
        return $this->prepare(
            normalized: parent::prepareForRegistration($attributes),
            transport: (string) ($attributes['transport'] ?? Channel::TransportHttp),
            mode: (string) ($attributes['mode'] ?? 'remote'),
        );
    }

    public function prepareForUpdate(Channel $channel, array $attributes): array
    {
        $normalized = parent::prepareForUpdate($channel, $attributes);
        $transport = (string) ($normalized['transport'] ?? $channel->transport ?? Channel::TransportHttp);
        $mode = (string) ($normalized['mode'] ?? 'remote');

        return $this->prepare($normalized, $transport, $mode);
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    protected function prepare(array $normalized, string $transport, string $mode): array
    {
        $transport = strtolower(trim($transport));
        $mode = strtolower(trim($mode));

        if ($mode === 'local') {
            return $normalized;
        }

        if (! in_array($transport, [Channel::TransportHttp, Channel::TransportWebsocket], true)) {
            return $normalized;
        }

        $endpointUrl = $normalized['endpoint_url'] ?? null;

        if (! is_string($endpointUrl) || trim($endpointUrl) === '') {
            return $normalized;
        }

        $configuredTools = $normalized['allowed_tools'] ?? null;

        if (is_array($configuredTools) && $configuredTools !== []) {
            return $normalized;
        }

        $normalized['allowed_tools'] = $this->remoteEndpointClient->listTools(
            endpointUrl: trim($endpointUrl),
            headers: $this->headersFromCredentials($normalized['credentials'] ?? []),
        );

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    protected function headersFromCredentials(mixed $credentials): array
    {
        if (! is_array($credentials)) {
            return [];
        }

        $headers = $credentials['headers'] ?? [];

        if (! is_array($headers)) {
            return [];
        }

        return collect($headers)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && trim($key) !== '' && is_string($value))
            ->mapWithKeys(fn (string $value, string $key): array => [trim($key) => $value])
            ->all();
    }
}
