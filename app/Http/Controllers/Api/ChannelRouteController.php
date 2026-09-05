<?php

namespace App\Http\Controllers\Api;

use App\Events\Server\Channels\MappingChannelRoute;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Channel\StoreChannelRouteRequest;
use App\Http\Requests\Server\Channel\UpdateChannelRouteRequest;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRoute;
use App\Models\Server\User;
use App\Support\Channels\ChannelAccess;
use App\Support\Channels\ChannelApiResolver;
use App\Support\Channels\ChannelRouteIngress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelRouteController extends Controller
{
    public function __construct(
        protected ChannelAccess $channelAccess,
        protected ChannelApiResolver $channelApiResolver,
        protected ChannelRouteIngress $channelRouteIngress,
    ) {}

    public function index(Request $request, string $channel): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = $this->channelApiResolver->channel($channel);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to view this channel.');

        $routes = $registeredChannel->routes()
            ->latest('id')
            ->get()
            ->map(fn (ChannelRoute $route): array => $this->mapRoute($registeredChannel, $route))
            ->all();

        return response()->json([
            'data' => $routes,
        ]);
    }

    public function store(StoreChannelRouteRequest $request, string $channel): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = $this->channelApiResolver->channel($channel);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to create routes for this channel.');

        $route = $registeredChannel->routes()->create($this->routeAttributes($request->validated()));

        return response()->json([
            'data' => $this->mapRoute($registeredChannel, $route),
        ], 201);
    }

    public function update(UpdateChannelRouteRequest $request, string $channel, string $route): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = $this->channelApiResolver->channel($channel);
        $registeredRoute = $this->channelApiResolver->route($registeredChannel, $route);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to update this channel route.');

        $registeredRoute->fill($this->routeAttributes($request->validated(), update: true))->save();

        return response()->json([
            'data' => $this->mapRoute($registeredChannel, $registeredRoute->fresh() ?? $registeredRoute),
        ]);
    }

    public function destroy(Request $request, string $channel, string $route): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = $this->channelApiResolver->channel($channel);
        $registeredRoute = $this->channelApiResolver->route($registeredChannel, $route);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to delete this channel route.');

        $registeredRoute->delete();

        return response()->json(status: 204);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function routeAttributes(array $attributes, bool $update = false): array
    {
        $values = collect([
            'name' => array_key_exists('name', $attributes) ? $this->stringValue($attributes['name']) : null,
            'label' => array_key_exists('label', $attributes) ? $this->stringValue($attributes['label']) : null,
            'status' => array_key_exists('status', $attributes) ? $this->stringValue($attributes['status']) : ($update ? null : Channel::StatusActive),
            'direction' => array_key_exists('direction', $attributes) ? $this->stringValue($attributes['direction']) : ($update ? null : Channel::DirectionBidirectional),
            'config' => array_key_exists('config', $attributes) && is_array($attributes['config']) ? $attributes['config'] : ($update ? null : []),
            'data' => array_key_exists('data', $attributes) && is_array($attributes['data']) ? $attributes['data'] : ($update ? null : []),
            'meta' => array_key_exists('meta', $attributes) && is_array($attributes['meta']) ? $attributes['meta'] : ($update ? null : []),
        ]);

        return $values->filter(fn (mixed $value): bool => $value !== null)->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapRoute(Channel $channel, ChannelRoute $route): array
    {
        $payload = [
            'id' => $route->ulid,
            'channel_id' => $channel->uuid,
            'protocol' => $channel->protocolKey(),
            'name' => $route->name,
            'label' => $route->label,
            'status' => $route->status,
            'direction' => $route->direction,
            'config' => is_array($route->config) ? $route->config : [],
            'data' => is_array($route->data) ? $route->data : [],
            'meta' => is_array($route->meta) ? $route->meta : [],
            'inbound' => $this->channelRouteIngress->descriptor($route),
            'outbound' => [
                'enabled' => in_array($this->normalizeDirection($route->direction), [Channel::DirectionOutbound, Channel::DirectionBidirectional], true),
                'transport' => $this->normalizedTransport(data_get($route->config, 'outbound.transport') ?? data_get($route->config, 'transport')),
                'endpoint_url' => $this->stringValue(data_get($route->config, 'outbound.endpoint_url') ?? data_get($route->config, 'endpoint_url')),
            ],
            'addresses_count' => $route->addresses()->count(),
            'created_at' => optional($route->created_at)?->toIso8601String(),
            'updated_at' => optional($route->updated_at)?->toIso8601String(),
        ];
        $event = new MappingChannelRoute($channel, $route, $payload);
        event($event);

        return $event->payload;
    }

    protected function canManageChannel(User $actor, Channel $channel): bool
    {
        return $this->channelAccess->canManage($actor, $channel);
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function normalizedTransport(mixed $value): ?string
    {
        $transport = $this->stringValue($value);

        return $transport !== null ? strtolower($transport) : null;
    }

    protected function normalizeDirection(mixed $value): string
    {
        $direction = $this->stringValue($value);
        $direction = $direction !== null ? strtolower($direction) : null;

        return in_array($direction, [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional], true)
            ? $direction
            : Channel::DirectionBidirectional;
    }
}
