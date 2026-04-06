<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Channel\StoreChannelRouteRequest;
use App\Http\Requests\Server\Channel\UpdateChannelRouteRequest;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRoute;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChannelRouteController extends Controller
{
    public function index(Request $request, int $channel): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
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

    public function store(StoreChannelRouteRequest $request, int $channel): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to create routes for this channel.');

        $route = $registeredChannel->routes()->create($this->routeAttributes($request->validated()));

        return response()->json([
            'data' => $this->mapRoute($registeredChannel, $route),
        ], 201);
    }

    public function update(UpdateChannelRouteRequest $request, int $channel, int $route): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        $registeredRoute = $registeredChannel->routes()->findOrFail($route);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to update this channel route.');

        $registeredRoute->fill($this->routeAttributes($request->validated(), update: true))->save();

        return response()->json([
            'data' => $this->mapRoute($registeredChannel, $registeredRoute->fresh() ?? $registeredRoute),
        ]);
    }

    public function destroy(Request $request, int $channel, int $route): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        $registeredRoute = $registeredChannel->routes()->findOrFail($route);
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
        return [
            'id' => $route->id,
            'channel_id' => $channel->id,
            'channel_uuid' => $channel->uuid,
            'protocol' => $channel->protocolKey(),
            'name' => $route->name,
            'label' => $route->label,
            'status' => $route->status,
            'direction' => $route->direction,
            'config' => is_array($route->config) ? $route->config : [],
            'data' => is_array($route->data) ? $route->data : [],
            'meta' => is_array($route->meta) ? $route->meta : [],
            'addresses_count' => $route->addresses()->count(),
            'created_at' => optional($route->created_at)?->toIso8601String(),
            'updated_at' => optional($route->updated_at)?->toIso8601String(),
        ];
    }

    protected function canManageChannel(User $actor, Channel $channel): bool
    {
        $relation = $channel->relations()->latest('id')->first();
        $owner = $relation?->relationable;

        if (! $owner instanceof Model) {
            return false;
        }

        return match (true) {
            $owner instanceof User => $owner->id === $actor->id,
            $owner instanceof Space => Gate::forUser($actor)->check('view', $owner),
            $owner instanceof Thread => Gate::forUser($actor)->check('view', $owner),
            default => false,
        };
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
