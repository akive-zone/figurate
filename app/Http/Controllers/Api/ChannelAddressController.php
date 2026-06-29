<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Channel\StoreChannelAddressRequest;
use App\Http\Requests\Server\Channel\UpdateChannelAddressRequest;
use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRoute;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Support\Channels\ChannelAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChannelAddressController extends Controller
{
    public function __construct(
        protected ChannelAccess $channelAccess,
    ) {}

    public function index(Request $request, int $channel, int $route): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        [$registeredChannel, $registeredRoute] = $this->resolveChannelRoute($channel, $route);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to view this channel route.');

        $addresses = $registeredRoute->addresses()
            ->latest('id')
            ->get()
            ->map(fn (ChannelAddress $address): array => $this->mapAddress($registeredChannel, $registeredRoute, $address))
            ->all();

        return response()->json([
            'data' => $addresses,
        ]);
    }

    public function store(StoreChannelAddressRequest $request, int $channel, int $route): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        [$registeredChannel, $registeredRoute] = $this->resolveChannelRoute($channel, $route);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to create addresses for this channel route.');

        $validated = $request->validated();
        [$addressableType, $addressable] = $this->resolveAddressable(
            (string) $validated['addressable_type'],
            $validated['addressable_id'] ?? null,
            $actor,
        );

        $address = $registeredRoute->addresses()->create(array_merge(
            $this->addressAttributes($validated),
            [
                'addressable_type' => $addressable->getMorphClass(),
                'addressable_id' => $addressable->getKey(),
            ],
        ));

        return response()->json([
            'data' => $this->mapAddress($registeredChannel, $registeredRoute, $address),
            'addressable' => [
                'type' => $addressableType,
                'id' => $this->publicIdentifier($addressable),
            ],
        ], 201);
    }

    public function update(UpdateChannelAddressRequest $request, int $channel, int $route, int $address): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        [$registeredChannel, $registeredRoute] = $this->resolveChannelRoute($channel, $route);
        $registeredAddress = $registeredRoute->addresses()->findOrFail($address);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to update this channel address.');

        $registeredAddress->fill($this->addressAttributes($request->validated(), update: true))->save();

        return response()->json([
            'data' => $this->mapAddress($registeredChannel, $registeredRoute, $registeredAddress->fresh() ?? $registeredAddress),
        ]);
    }

    public function destroy(Request $request, int $channel, int $route, int $address): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        [$registeredChannel, $registeredRoute] = $this->resolveChannelRoute($channel, $route);
        $registeredAddress = $registeredRoute->addresses()->findOrFail($address);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to delete this channel address.');

        $registeredAddress->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array{0: Channel, 1: ChannelRoute}
     */
    protected function resolveChannelRoute(int $channel, int $route): array
    {
        $registeredChannel = Channel::query()->findOrFail($channel);
        $registeredRoute = $registeredChannel->routes()->findOrFail($route);

        return [$registeredChannel, $registeredRoute];
    }

    /**
     * @return array{0: string, 1: Model}
     */
    protected function resolveAddressable(string $addressableType, mixed $addressableId, User $actor): array
    {
        $resolvedType = strtolower(trim($addressableType));

        if ($resolvedType === 'user') {
            if ($addressableId === null || $addressableId === '' || $addressableId === 'me') {
                return ['user', $actor];
            }

            abort(403, 'Not authorized for this user address.');
        }

        if ($resolvedType === 'space') {
            abort_if(! is_string($addressableId) || trim($addressableId) === '', 422, 'addressable_id is required for space addresses.');

            $space = Space::query()->where('uuid', $addressableId)->firstOrFail();
            Gate::forUser($actor)->authorize('view', $space);

            return ['space', $space];
        }

        if ($resolvedType === 'thread') {
            abort_if(! is_string($addressableId) || trim($addressableId) === '', 422, 'addressable_id is required for thread addresses.');

            $thread = Thread::query()->where('uuid', $addressableId)->firstOrFail();
            Gate::forUser($actor)->authorize('view', $thread);

            return ['thread', $thread];
        }

        abort(422, 'Unsupported addressable type.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function addressAttributes(array $attributes, bool $update = false): array
    {
        return collect([
            'label' => array_key_exists('label', $attributes) ? $this->stringValue($attributes['label']) : null,
            'provider' => array_key_exists('provider', $attributes) ? $this->stringValue($attributes['provider']) : null,
            'target' => array_key_exists('target', $attributes) ? $this->stringValue($attributes['target']) : null,
            'target_type' => array_key_exists('target_type', $attributes) ? $this->stringValue($attributes['target_type']) : null,
            'status' => array_key_exists('status', $attributes) ? $this->stringValue($attributes['status']) : ($update ? null : Channel::StatusActive),
            'direction' => array_key_exists('direction', $attributes) ? $this->stringValue($attributes['direction']) : ($update ? null : Channel::DirectionBidirectional),
            'data' => array_key_exists('data', $attributes) && is_array($attributes['data']) ? $attributes['data'] : ($update ? null : []),
            'meta' => array_key_exists('meta', $attributes) && is_array($attributes['meta']) ? $attributes['meta'] : ($update ? null : []),
        ])->filter(fn (mixed $value): bool => $value !== null)->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapAddress(Channel $channel, ChannelRoute $route, ChannelAddress $address): array
    {
        $addressable = $address->addressable;

        return [
            'id' => $address->id,
            'channel' => [
                'id' => $channel->id,
                'uuid' => $channel->uuid,
                'protocol' => $channel->protocolKey(),
            ],
            'route' => [
                'id' => $route->id,
                'name' => $route->name,
            ],
            'addressable' => [
                'type' => $addressable instanceof Model ? strtolower(class_basename($addressable)) : null,
                'id' => $addressable instanceof Model ? $this->publicIdentifier($addressable) : null,
            ],
            'label' => $address->label,
            'provider' => $address->provider,
            'target' => $address->target,
            'target_type' => $address->target_type,
            'status' => $address->status,
            'direction' => $address->direction,
            'data' => is_array($address->data) ? $address->data : [],
            'meta' => is_array($address->meta) ? $address->meta : [],
            'created_at' => optional($address->created_at)?->toIso8601String(),
            'updated_at' => optional($address->updated_at)?->toIso8601String(),
        ];
    }

    protected function canManageChannel(User $actor, Channel $channel): bool
    {
        return $this->channelAccess->canManage($actor, $channel);
    }

    protected function publicIdentifier(Model $model): mixed
    {
        $uuid = $model->getAttribute('uuid');

        if (is_string($uuid) && $uuid !== '') {
            return $uuid;
        }

        return $model->getKey();
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
