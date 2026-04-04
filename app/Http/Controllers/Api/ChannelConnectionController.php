<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Channel\StoreChannelConnectionRequest;
use App\Http\Requests\Server\Channel\UpdateChannelConnectionRequest;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Support\Channels\ChannelConnectionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChannelConnectionController extends Controller
{
    public function index(Request $request, int $channel): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);

        $connections = $registeredChannel->connections()
            ->latest('id')
            ->get()
            ->filter(fn (ChannelRelation $connection): bool => $this->canManageConnection($actor, $connection))
            ->values()
            ->map(fn (ChannelRelation $connection): array => $this->mapConnection($registeredChannel, $connection))
            ->all();

        return response()->json([
            'data' => $connections,
        ]);
    }

    public function store(
        StoreChannelConnectionRequest $request,
        int $channel,
        ChannelConnectionRegistry $channelConnectionRegistry
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        [$ownerType, $owner] = $this->resolveOwnerTarget(
            (string) $request->validated('owner_type'),
            $request->validated('owner_id'),
            $actor,
        );

        $connection = $channelConnectionRegistry->create(
            $owner,
            $registeredChannel,
            $request->validated(),
        );

        return response()->json([
            'data' => $this->mapConnection($registeredChannel, $connection),
            'owner' => [
                'type' => $ownerType,
                'id' => $this->publicIdentifier($owner),
            ],
        ], 201);
    }

    public function update(
        UpdateChannelConnectionRequest $request,
        int $channel,
        int $connection,
        ChannelConnectionRegistry $channelConnectionRegistry
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        $registeredConnection = $registeredChannel->connections()->findOrFail($connection);
        abort_unless($this->canManageConnection($actor, $registeredConnection), 403, 'Not authorized to update this channel connection.');

        $owner = $registeredConnection->relationable;
        abort_unless($owner instanceof Model, 404, 'Channel connection owner could not be resolved.');

        $updatedConnection = $channelConnectionRegistry->update(
            $owner,
            $registeredChannel,
            $registeredConnection,
            $request->validated(),
        );

        return response()->json([
            'data' => $this->mapConnection($registeredChannel, $updatedConnection),
        ]);
    }

    public function destroy(Request $request, int $channel, int $connection): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        $registeredConnection = $registeredChannel->connections()->findOrFail($connection);
        abort_unless($this->canManageConnection($actor, $registeredConnection), 403, 'Not authorized to delete this channel connection.');

        $registeredConnection->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array{0: string, 1: Model}
     */
    protected function resolveOwnerTarget(string $ownerType, mixed $ownerId, User $actor): array
    {
        $resolvedType = strtolower(trim($ownerType));

        if ($resolvedType === 'user') {
            if ($ownerId === null || $ownerId === '' || $ownerId === 'me') {
                return ['user', $actor];
            }

            abort(403, 'Not authorized for this user context.');
        }

        if ($resolvedType === 'space') {
            abort_if(! is_string($ownerId) || trim($ownerId) === '', 422, 'owner_id is required for space owners.');

            $space = Space::query()->where('uuid', $ownerId)->firstOrFail();
            Gate::forUser($actor)->authorize('view', $space);

            return ['space', $space];
        }

        if ($resolvedType === 'thread') {
            abort_if(! is_string($ownerId) || trim($ownerId) === '', 422, 'owner_id is required for thread owners.');

            $thread = Thread::query()->where('uuid', $ownerId)->firstOrFail();
            Gate::forUser($actor)->authorize('view', $thread);

            return ['thread', $thread];
        }

        abort(422, 'Unsupported owner type.');
    }

    protected function canManageConnection(User $actor, ChannelRelation $connection): bool
    {
        $owner = $connection->relationable;

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

    /**
     * @return array<string, mixed>
     */
    protected function mapConnection(Channel $channel, ChannelRelation $connection): array
    {
        $owner = $connection->relationable;
        $config = is_array($connection->config) ? $connection->config : [];
        $transport = is_string($config['transport'] ?? null) ? trim((string) $config['transport']) : null;
        $mode = is_string($config['mode'] ?? null) ? trim((string) $config['mode']) : null;
        $endpointUrl = is_string($config['endpoint_url'] ?? null) ? trim((string) $config['endpoint_url']) : null;
        $handler = is_string($config['handler'] ?? null) ? trim((string) $config['handler']) : null;
        $authType = is_string($config['auth_type'] ?? null) ? trim((string) $config['auth_type']) : null;
        $credentials = is_array($config['credentials'] ?? null) ? $config['credentials'] : [];

        return [
            'id' => $connection->id,
            'channel' => [
                'id' => $channel->id,
                'uuid' => $channel->uuid,
                'driver' => $channel->driver,
                'name' => $channel->server,
                'label' => $channel->label,
            ],
            'owner' => [
                'type' => $owner instanceof Model ? strtolower(class_basename($owner)) : null,
                'id' => $owner instanceof Model ? $this->publicIdentifier($owner) : null,
            ],
            'kind' => $connection->kind,
            'status' => $connection->status,
            'direction' => $connection->direction,
            'transport' => $transport,
            'mode' => $mode,
            'endpoint_url' => $endpointUrl,
            'handler' => $handler,
            'auth_type' => $authType,
            'has_credentials' => $credentials !== [],
            'config' => $config,
            'data' => is_array($connection->data) ? $connection->data : [],
            'meta' => is_array($connection->meta) ? $connection->meta : [],
            'created_at' => optional($connection->created_at)?->toIso8601String(),
            'updated_at' => optional($connection->updated_at)?->toIso8601String(),
        ];
    }

    protected function publicIdentifier(Model $owner): mixed
    {
        return $owner->getAttribute('uuid') ?? $owner->getKey();
    }
}
