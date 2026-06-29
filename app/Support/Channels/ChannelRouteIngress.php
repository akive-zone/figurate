<?php

namespace App\Support\Channels;

use App\Features\Actions\Conversation\InboundMessageEnvelope;
use App\Features\Operations\Chat\IngestInboundChatMessageOperation;
use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRoute;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use Illuminate\Http\Request;

class ChannelRouteIngress
{
    public function __construct(
        protected ChannelDriverRegistry $channelDriverRegistry,
        protected IngestInboundChatMessageOperation $ingestInboundChatMessageOperation,
        protected ChannelSkillContextResolver $channelSkillContextResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function descriptor(ChannelRoute $route): array
    {
        $config = is_array($route->config) ? $route->config : [];
        $direction = $this->normalizeDirection($route->direction);
        $transport = $this->inboundTransport($config);

        if (! in_array($direction, [Channel::DirectionInbound, Channel::DirectionBidirectional], true) || $transport === null) {
            return [
                'enabled' => false,
                'transport' => $transport,
                'url' => null,
                'auth' => null,
            ];
        }

        if (in_array($transport, [Channel::TransportHttp, Channel::TransportWebhook], true)) {
            return [
                'enabled' => true,
                'transport' => $transport,
                'url' => route('webhook-client-channel_route_inbound', ['route' => $route->id]),
                'auth' => $this->authDescriptor($config),
            ];
        }

        if ($transport === Channel::TransportWebsocket) {
            return [
                'enabled' => false,
                'transport' => $transport,
                'url' => null,
                'auth' => $this->authDescriptor($config),
                'message' => 'WebSocket inbound requires a dedicated socket server and is not exposed as an HTTP ingress endpoint.',
            ];
        }

        return [
            'enabled' => false,
            'transport' => $transport,
            'url' => null,
            'auth' => $this->authDescriptor($config),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receive(ChannelRoute $route, Request $request): array
    {
        $config = is_array($route->config) ? $route->config : [];
        $this->authorizeInbound($request, $config);

        return $this->receiveStored(
            $route,
            $this->requestPayload($request),
            is_array($request->headers->all()) ? $request->headers->all() : [],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public function receiveStored(ChannelRoute $route, array $payload, array $headers = []): array
    {
        $channel = $route->channel;

        if (! $channel instanceof Channel) {
            abort(404, 'Channel route channel could not be resolved.');
        }

        if (! in_array($channel->status, [Channel::StatusActive, null], true) || ! in_array($route->status, [Channel::StatusActive, null], true)) {
            abort(422, 'Channel route is not active.');
        }

        $direction = $this->normalizeDirection($route->direction);
        $config = is_array($route->config) ? $route->config : [];
        $transport = $this->inboundTransport($config);

        if (! in_array($direction, [Channel::DirectionInbound, Channel::DirectionBidirectional], true) || ! in_array($transport, [Channel::TransportHttp, Channel::TransportWebhook], true)) {
            abort(422, 'Channel route does not support HTTP or webhook inbound ingress.');
        }
        $normalized = $this->channelDriverRegistry
            ->resolveByChannel($channel)
            ->normalizeInbound($channel, $payload);
        $address = $this->resolveAddress($route, $normalized);
        $thread = $this->resolveThread($route, $address, $normalized);
        $skillContext = $this->channelSkillContextResolver->resolve($channel, $route, $address);
        $text = $this->normalizedText($normalized['text'] ?? null);

        if ($text === null) {
            abort(422, 'Inbound payload did not contain message text.');
        }

        $provider = $this->normalizedString($normalized['provider'] ?? null)
            ?? $this->normalizedString($address->provider)
            ?? $channel->protocolKey();
        $externalActorId = $this->resolveExternalActorId($normalized);
        $post = $this->ingestInboundChatMessageOperation->run(new InboundMessageEnvelope(
            thread: $thread,
            protocol: $channel->protocolKey(),
            provider: $provider,
            externalActorId: $externalActorId,
            text: $text,
            payload: $this->normalizedPayload($payload, $normalized, $address, $skillContext, $headers),
        ));

        return [
            'route' => $route,
            'address' => $address,
            'thread' => $thread,
            'post' => $post,
            'transport' => $transport,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function inboundTransport(array $config): ?string
    {
        $transport = $config['inbound']['transport'] ?? $config['transport'] ?? null;

        return $this->normalizedString($transport);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    protected function authDescriptor(array $config): ?array
    {
        $type = $this->normalizedString(data_get($config, 'inbound.auth.type'));

        if ($type === null || $type === 'none') {
            return [
                'type' => 'none',
            ];
        }

        return [
            'type' => $type,
            'header' => $this->normalizedString(data_get($config, 'inbound.auth.header')) ?? 'X-Channel-Key',
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function authorizeInbound(Request $request, array $config): void
    {
        $type = $this->normalizedString(data_get($config, 'inbound.auth.type'));

        if ($type === null || $type === 'none') {
            return;
        }

        $expected = $this->normalizedString(data_get($config, 'inbound.auth.secret'))
            ?? $this->normalizedString(data_get($config, 'inbound.auth.token'))
            ?? $this->normalizedString(data_get($config, 'inbound.auth.value'));

        if ($expected === null) {
            abort(422, 'Channel route inbound auth is configured without a secret or token.');
        }

        if ($type === 'bearer') {
            $token = $this->normalizedString($request->bearerToken());

            abort_unless($token !== null && hash_equals($expected, $token), 401, 'Invalid bearer token for channel route ingress.');

            return;
        }

        $header = $this->normalizedString(data_get($config, 'inbound.auth.header')) ?? 'X-Channel-Key';
        $provided = $this->normalizedString($request->header($header));

        abort_unless($provided !== null && hash_equals($expected, $provided), 401, 'Invalid header secret for channel route ingress.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestPayload(Request $request): array
    {
        $payload = $request->all();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function resolveAddress(ChannelRoute $route, array $normalized): ChannelAddress
    {
        $addresses = $route->addresses()
            ->with('addressable')
            ->where('status', Channel::StatusActive)
            ->get();

        $target = $this->normalizedString($normalized['target'] ?? null)
            ?? $this->normalizedString($normalized['provider_identifier'] ?? null);
        $provider = $this->normalizedString($normalized['provider'] ?? null);

        if ($target !== null) {
            $threadMatch = $addresses->first(function (ChannelAddress $address) use ($provider, $target): bool {
                if (! $address->addressable instanceof Thread) {
                    return false;
                }

                return $address->target === $target
                    && ($provider === null || $address->provider === null || $address->provider === $provider);
            });

            if ($threadMatch instanceof ChannelAddress) {
                return $threadMatch;
            }

            $anyMatch = $addresses->first(function (ChannelAddress $address) use ($provider, $target): bool {
                return $address->target === $target
                    && ($provider === null || $address->provider === null || $address->provider === $provider);
            });

            if ($anyMatch instanceof ChannelAddress) {
                return $anyMatch;
            }
        }

        if ($addresses->count() === 1) {
            return $addresses->firstOrFail();
        }

        $spaceFallback = $addresses->first(fn (ChannelAddress $address): bool => $address->addressable instanceof Space);

        if ($spaceFallback instanceof ChannelAddress) {
            return $spaceFallback;
        }

        abort(404, 'No channel address could be resolved for this inbound payload.');
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function resolveThread(ChannelRoute $route, ChannelAddress $address, array $normalized): Thread
    {
        $addressable = $address->addressable;

        if ($addressable instanceof Thread) {
            return $addressable;
        }

        if (! $addressable instanceof Space) {
            abort(422, 'Channel address does not resolve to a supported target.');
        }

        $target = $this->normalizedString($normalized['target'] ?? null)
            ?? $this->normalizedString($normalized['provider_identifier'] ?? null);

        if ($target !== null) {
            $existingThreadAddress = $route->addresses()
                ->where('addressable_type', (new Thread)->getMorphClass())
                ->where('target', $target)
                ->latest('id')
                ->first();

            if ($existingThreadAddress instanceof ChannelAddress) {
                $existingThread = $existingThreadAddress->addressable;

                if ($existingThread instanceof Thread) {
                    return $existingThread;
                }
            }
        }

        $thread = $addressable->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => $target !== null ? "Channel {$target}" : 'Channel Thread',
            'phase' => 'request_intake',
            'status' => 'open',
        ]);

        $thread->actors()->create([
            'actorable_type' => ThreadActor::ActorRequestAgent,
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        $route->addresses()->create([
            'addressable_type' => $thread->getMorphClass(),
            'addressable_id' => $thread->getKey(),
            'label' => $address->label,
            'provider' => $address->provider ?? $this->normalizedString($normalized['provider'] ?? null),
            'target' => $target ?? $address->target,
            'target_type' => $address->target_type ?? $this->normalizedString($normalized['target_type'] ?? null),
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'data' => is_array($address->data) ? $address->data : [],
            'meta' => is_array($address->meta) ? $address->meta : [],
        ]);

        return $thread;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function resolveExternalActorId(array $normalized): string
    {
        $sender = $normalized['sender'] ?? null;

        if (is_string($sender) && trim($sender) !== '') {
            return trim($sender);
        }

        if (is_array($sender)) {
            foreach (['id', 'uuid', 'phone', 'handle', 'email', 'name'] as $key) {
                $value = $this->normalizedString($sender[$key] ?? null);

                if ($value !== null) {
                    return $value;
                }
            }
        }

        return $this->normalizedString($normalized['provider_identifier'] ?? null)
            ?? 'external';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    protected function normalizedPayload(array $payload, array $normalized, ChannelAddress $address, array $skillContext, array $headers = []): array
    {
        $messageId = $this->normalizedString($normalized['provider_message_id'] ?? null);
        $target = $this->normalizedString($normalized['target'] ?? null)
            ?? $this->normalizedString($normalized['provider_identifier'] ?? null)
            ?? $address->target;

        return [
            ...$payload,
            'id' => $messageId ?? ($payload['id'] ?? null),
            'message' => [
                'id' => $messageId ?? data_get($payload, 'message.id') ?? ($payload['id'] ?? null),
                'text' => $this->normalizedText($normalized['text'] ?? null),
            ],
            'target' => $target,
            'provider_identifier' => $target,
            'headers' => $headers,
            'skill_context' => $skillContext,
            '_normalized' => $normalized,
        ];
    }

    protected function normalizeDirection(mixed $value): string
    {
        $direction = $this->normalizedString($value);

        return in_array($direction, [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional], true)
            ? $direction
            : Channel::DirectionBidirectional;
    }

    protected function normalizedText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text !== '' ? $text : null;
    }

    protected function normalizedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
