<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Channel\StoreChannelRequest;
use App\Http\Requests\Server\Channel\UpdateChannelRequest;
use App\Models\Server\Channel;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Support\Channels\ChannelDriverRegistry;
use App\Support\Channels\ChannelRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChannelController extends Controller
{
    public function __construct(
        protected ChannelDriverRegistry $channelDriverRegistry,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $ownerType = $this->ownerTypeFromRequest($request);
        $ownerId = $request->query('owner_id', $request->query('context_id'));
        $protocol = $this->trimmedString($request->query('protocol'))
            ?? $this->trimmedString($request->query('system'))
            ?? $this->trimmedString($request->query('driver'));
        $kind = $this->trimmedString($request->query('kind'));
        $query = Channel::query()->latest('id');

        if ($protocol !== null) {
            $query->where('driver', $protocol);
        }

        if ($ownerType !== null) {
            [, $owner] = $this->resolveOwnerTarget($ownerType, $ownerId, $actor);

            $query->whereHas('relations', function ($relationQuery) use ($owner, $kind): void {
                $relationQuery
                    ->where('relationable_type', $owner->getMorphClass())
                    ->where('relationable_id', $owner->getKey());

                if ($kind !== null) {
                    $relationQuery->where('kind', $kind);
                }
            });
        } else {
            $query->where(function ($scopedQuery) use ($actor): void {
                $scopedQuery->orWhere(function ($userQuery) use ($actor): void {
                    $userQuery->whereHas('relations', function ($relationQuery) use ($actor): void {
                        $relationQuery
                            ->where('relationable_type', $actor->getMorphClass())
                            ->where('relationable_id', $actor->getKey());
                    });
                });

                $spaceIds = Space::query()
                    ->whereHas('actorStates', function ($actorStateQuery) use ($actor): void {
                        $actorStateQuery
                            ->where('actorable_type', $actor->getMorphClass())
                            ->where('actorable_id', $actor->getKey());
                    })
                    ->pluck('id');

                if ($spaceIds->isNotEmpty()) {
                    $scopedQuery->orWhere(function ($channelQuery) use ($spaceIds): void {
                        $channelQuery->whereHas('relations', function ($relationQuery) use ($spaceIds): void {
                            $relationQuery
                                ->where('relationable_type', (new Space)->getMorphClass())
                                ->whereIn('relationable_id', $spaceIds->all());
                        });
                    });
                }

                $threadIds = Thread::query()
                    ->whereHasMorph('threadable', [Space::class], function ($threadableQuery) use ($actor): void {
                        $threadableQuery->whereHas('actorStates', function ($actorStateQuery) use ($actor): void {
                            $actorStateQuery
                                ->where('actorable_type', $actor->getMorphClass())
                                ->where('actorable_id', $actor->getKey());
                        });
                    })
                    ->pluck('id');

                if ($threadIds->isNotEmpty()) {
                    $scopedQuery->orWhere(function ($channelQuery) use ($threadIds): void {
                        $channelQuery->whereHas('relations', function ($relationQuery) use ($threadIds): void {
                            $relationQuery
                                ->where('relationable_type', (new Thread)->getMorphClass())
                                ->whereIn('relationable_id', $threadIds->all());
                        });
                    });
                }
            });
        }

        $channels = $query->get()
            ->filter(fn (Channel $channel): bool => $this->canManageChannel($actor, $channel))
            ->values()
            ->map(fn (Channel $channel): array => $this->mapChannel($channel))
            ->all();

        return response()->json([
            'data' => $channels,
        ]);
    }

    public function store(StoreChannelRequest $request, ChannelRegistry $channelRegistry): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        [$ownerType, $owner] = $this->resolveOwnerTarget(
            (string) $validated['owner_type'],
            $validated['owner_id'] ?? null,
            $actor,
        );
        $channel = $channelRegistry->register($owner, $validated);

        return response()->json([
            'data' => $this->mapChannel($channel),
            'owner' => [
                'type' => $ownerType,
                'id' => $this->publicIdentifier($owner),
            ],
        ], 201);
    }

    public function update(UpdateChannelRequest $request, int $channel, ChannelRegistry $channelRegistry): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to update this channel.');

        $owner = $this->resolveOwnerContext($registeredChannel);
        abort_unless($owner instanceof Model, 404, 'Channel owner could not be resolved.');

        $updatedChannel = $channelRegistry->update($owner, $registeredChannel, $request->validated());

        return response()->json([
            'data' => $this->mapChannel($updatedChannel),
        ]);
    }

    public function destroy(Request $request, int $channel): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);

        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to delete this channel.');

        $registeredChannel->delete();

        return response()->json(status: 204);
    }

    protected function ownerTypeFromRequest(Request $request): ?string
    {
        return $this->trimmedString($request->query('owner_type'))
            ?? $this->trimmedString($request->query('context_type'));
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

    protected function canManageChannel(User $actor, Channel $channel): bool
    {
        $owner = $this->resolveOwnerContext($channel);

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
    protected function mapChannel(Channel $channel): array
    {
        $owner = $this->resolveOwnerContext($channel);
        $ownerType = $owner instanceof Model ? strtolower(class_basename($owner)) : null;
        $relation = $channel->relations()
            ->latest('id')
            ->first();
        $driver = $this->channelDriverRegistry->resolveByChannel($channel);

        return [
            'id' => $channel->id,
            'uuid' => $channel->uuid,
            'name' => $channel->server,
            'server' => $channel->server,
            'label' => $channel->label,
            'protocol' => $channel->protocolKey(),
            'driver' => $channel->protocolKey(),
            'system' => $channel->protocolKey(),
            'capabilities' => $driver->capabilities($channel),
            'enabled' => (bool) $channel->enabled,
            'priority' => $channel->priority,
            'transport' => $channel->transportKey(),
            'status' => $channel->status,
            'direction' => $relation?->direction ?? $channel->direction,
            'kind' => $relation?->kind,
            'endpoint_url' => $channel->endpoint_url,
            'handler' => $channel->handler,
            'allowed_tools' => is_array($channel->allowed_tools) ? $channel->allowed_tools : [],
            'auth_type' => $channel->auth_type,
            'owner' => [
                'type' => $ownerType,
                'id' => $owner instanceof Model ? $this->publicIdentifier($owner) : null,
            ],
            'created_at' => optional($channel->created_at)?->toIso8601String(),
            'updated_at' => optional($channel->updated_at)?->toIso8601String(),
        ];
    }

    protected function resolveOwnerContext(Channel $channel): ?Model
    {
        $ownerRelation = $channel->relations()
            ->latest('id')
            ->first();

        $relationable = $ownerRelation?->relationable;

        return $relationable instanceof Model ? $relationable : null;
    }

    protected function publicIdentifier(Model $model): mixed
    {
        $uuid = $model->getAttribute('uuid');

        if (is_string($uuid) && $uuid !== '') {
            return $uuid;
        }

        return $model->getKey();
    }

    protected function trimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
