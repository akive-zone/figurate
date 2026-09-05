<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Channel\StoreChannelRequest;
use App\Http\Requests\Server\Channel\UpdateChannelRequest;
use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Support\Channels\ChannelAccess;
use App\Support\Channels\ChannelApiResolver;
use App\Support\Channels\ChannelDriverRegistry;
use App\Support\Channels\ChannelLinkRepository;
use App\Support\Channels\ChannelRegistry;
use App\Support\Channels\ChannelSpaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChannelController extends Controller
{
    public function __construct(
        protected ChannelDriverRegistry $channelDriverRegistry,
        protected ChannelAccess $channelAccess,
        protected ChannelApiResolver $channelApiResolver,
        protected ChannelSpaceContext $channelSpaceContext,
        protected ChannelLinkRepository $channelLinks,
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
        $query = Channel::query()->latest('id');
        $owner = null;

        if ($protocol !== null) {
            $query->where('driver', $protocol);
        }

        if ($ownerType !== null) {
            [, $owner] = $this->resolveOwnerTarget($ownerType, $ownerId, $actor);
        }

        $channels = $query->get()
            ->filter(fn (Channel $channel): bool => $ownerType !== null
                ? $this->channelAccess->isAttachedTo($actor, $channel, $owner)
                : $this->canManageChannel($actor, $channel))
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
        $space = $this->channelSpaceContext->resolve(
            actor: $actor,
            ownerType: $ownerType,
            owner: $owner,
            spaceId: is_string($validated['space_id'] ?? null) ? $validated['space_id'] : null,
        );
        $channel = $channelRegistry->register($validated);
        $target = $owner instanceof Space || $owner instanceof Thread || $owner instanceof Post
            ? $owner
            : $space;
        $link = $this->channelLinks->create($channel, $space, $target, $validated, $actor);

        return response()->json([
            'data' => $this->mapChannel($channel),
            'link' => [
                'id' => $link->ulid,
                'type' => $link->type,
            ],
            'owner' => [
                'type' => $ownerType,
                'id' => $this->publicIdentifier($owner),
            ],
            'space' => [
                'id' => $this->publicIdentifier($space),
                'status' => $space->status,
            ],
        ], 201);
    }

    public function update(UpdateChannelRequest $request, string $channel, ChannelRegistry $channelRegistry): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = $this->channelApiResolver->channel($channel);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to update this channel.');

        $updatedChannel = $channelRegistry->update($registeredChannel, $request->validated());

        return response()->json([
            'data' => $this->mapChannel($updatedChannel),
        ]);
    }

    public function destroy(Request $request, string $channel): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = $this->channelApiResolver->channel($channel);

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

        if ($resolvedType === 'post') {
            abort_if(! is_string($ownerId) || trim($ownerId) === '', 422, 'owner_id is required for post owners.');

            $post = Post::query()->where('ulid', $ownerId)->firstOrFail();
            Gate::forUser($actor)->authorize('view', $post);

            return ['post', $post];
        }

        abort(422, 'Unsupported owner type.');
    }

    protected function canManageChannel(User $actor, Channel $channel): bool
    {
        return $this->channelAccess->canManage($actor, $channel);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapChannel(Channel $channel): array
    {
        $owner = $this->resolveOwnerContext($channel);
        $space = $this->resolveSpaceContext($channel, $owner);
        $ownerType = $owner instanceof Model ? strtolower(class_basename($owner)) : null;
        $link = $this->channelLinks->forChannel($channel)->first();
        $driver = $this->channelDriverRegistry->resolveByChannel($channel);

        return [
            'id' => $channel->uuid,
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
            'direction' => $link instanceof Post ? $this->channelLinks->direction($link) : $channel->direction,
            'link' => $link instanceof Post ? [
                'id' => $link->ulid,
                'type' => $link->type,
            ] : null,
            'endpoint_url' => $channel->endpoint_url,
            'handler' => $channel->handler,
            'allowed_tools' => is_array($channel->allowed_tools) ? $channel->allowed_tools : [],
            'auth_type' => $channel->auth_type,
            'owner' => [
                'type' => $ownerType,
                'id' => $owner instanceof Model ? $this->publicIdentifier($owner) : null,
            ],
            'space' => [
                'id' => $space instanceof Space ? $this->publicIdentifier($space) : null,
                'status' => $space instanceof Space ? $space->status : null,
            ],
            'config' => is_array($channel->config) ? $channel->config : [],
            'created_at' => optional($channel->created_at)?->toIso8601String(),
            'updated_at' => optional($channel->updated_at)?->toIso8601String(),
        ];
    }

    protected function resolveOwnerContext(Channel $channel): ?Model
    {
        $link = $this->channelLinks->forChannel($channel)->first();

        return $link instanceof Post
            ? $this->channelLinks->targets($link)->first()
            : null;
    }

    protected function resolveSpaceContext(Channel $channel, ?Model $owner = null): ?Space
    {
        if ($owner instanceof Space) {
            return $owner;
        }

        if ($owner instanceof Thread) {
            $threadable = $owner->relationLoaded('threadable') ? $owner->threadable : $owner->threadable()->first();

            if ($threadable instanceof Space) {
                return $threadable;
            }
        }

        if ($owner instanceof Post) {
            $parent = $owner->postable;

            return match (true) {
                $parent instanceof Space => $parent,
                $parent instanceof Thread && $parent->threadable instanceof Space => $parent->threadable,
                default => null,
            };
        }

        return null;
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
