<?php

namespace App\Http\Controllers\Server\Api;

use App\Ai\Support\Mcp\ContextServerRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\ContextServer\StoreContextServerRequest;
use App\Http\Requests\Server\ContextServer\UpdateContextServerRequest;
use App\Models\Server\Channel;
use App\Models\Server\ContextServer;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContextServerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $requestedContextType = $request->query('context_type');
        $requestedContextId = $request->query('context_id');

        $query = ContextServer::query()->latest('id');

        if (is_string($requestedContextType) && $requestedContextType !== '') {
            [$contextType, $context] = $this->resolveContextTarget(
                contextType: $requestedContextType,
                contextId: is_string($requestedContextId) ? $requestedContextId : null,
                actor: $actor,
            );

            $query->where('contextable_type', $context->getMorphClass())
                ->where('contextable_id', $context->getKey());
        } else {
            $query->where(function ($scopedQuery) use ($actor): void {
                $scopedQuery->orWhere(function ($userQuery) use ($actor): void {
                    $userQuery
                        ->where('contextable_type', $actor->getMorphClass())
                        ->where('contextable_id', $actor->getKey());
                });

                $channelIds = Channel::query()
                    ->whereHas('actorStates', function ($actorStateQuery) use ($actor): void {
                        $actorStateQuery
                            ->where('actorable_type', $actor->getMorphClass())
                            ->where('actorable_id', $actor->getKey());
                    })
                    ->pluck('id');

                if ($channelIds->isNotEmpty()) {
                    $scopedQuery->orWhere(function ($channelQuery) use ($channelIds): void {
                        $channelQuery
                            ->where('contextable_type', (new Channel)->getMorphClass())
                            ->whereIn('contextable_id', $channelIds->all());
                    });
                }
            });
        }

        $servers = $query->get()
            ->filter(fn (ContextServer $contextServer): bool => $this->canManageContextServer($actor, $contextServer))
            ->values()
            ->map(fn (ContextServer $contextServer): array => $this->mapContextServer($contextServer))
            ->all();

        return response()->json([
            'data' => $servers,
        ]);
    }

    public function store(
        StoreContextServerRequest $request,
        ContextServerRegistry $contextServerRegistry,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        [$contextType, $context] = $this->resolveContextTarget(
            contextType: (string) $validated['context_type'],
            contextId: $validated['context_id'] ?? null,
            actor: $actor,
        );

        $transport = strtolower((string) ($validated['transport'] ?? 'remote'));
        $credentials = is_array($validated['credentials'] ?? null) ? $validated['credentials'] : [];

        if ($transport === 'remote') {
            $endpointUrl = (string) ($validated['endpoint_url'] ?? '');

            $contextServer = $contextServerRegistry->registerRemoteServer(
                contextable: $context,
                server: (string) $validated['server'],
                endpointUrl: $endpointUrl,
                headers: is_array($credentials['headers'] ?? null) ? $credentials['headers'] : [],
                label: $validated['label'] ?? null,
                priority: (int) ($validated['priority'] ?? 0),
            );

            $contextServer->forceFill([
                'enabled' => (bool) ($validated['enabled'] ?? true),
                'auth_type' => $validated['auth_type'] ?? $contextServer->auth_type,
                'credentials' => $credentials !== [] ? $credentials : $contextServer->credentials,
                'allowed_tools' => is_array($validated['allowed_tools'] ?? null) ? $validated['allowed_tools'] : $contextServer->allowed_tools,
            ])->save();
        } else {
            /** @var ContextServer $contextServer */
            $contextServer = $context->contextServers()->updateOrCreate(
                ['server' => (string) $validated['server']],
                [
                    'label' => $validated['label'] ?? null,
                    'enabled' => (bool) ($validated['enabled'] ?? true),
                    'priority' => (int) ($validated['priority'] ?? 0),
                    'transport' => 'local',
                    'handler' => $validated['handler'] ?? null,
                    'allowed_tools' => $validated['allowed_tools'] ?? [],
                    'auth_type' => $validated['auth_type'] ?? null,
                    'credentials' => $credentials,
                ],
            );
        }

        return response()->json([
            'data' => $this->mapContextServer($contextServer),
            'context' => [
                'type' => $contextType,
                'id' => $this->publicIdentifier($context),
            ],
        ], 201);
    }

    public function update(UpdateContextServerRequest $request, int $contextServer): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $server = ContextServer::query()->findOrFail($contextServer);

        abort_unless($this->canManageContextServer($actor, $server), 403, 'Not authorized to update this context server.');

        $validated = $request->validated();

        if (array_key_exists('transport', $validated)) {
            $validated['transport'] = strtolower((string) $validated['transport']);
        }

        $server->fill($validated)->save();

        return response()->json([
            'data' => $this->mapContextServer($server->fresh() ?? $server),
        ]);
    }

    public function destroy(Request $request, int $contextServer): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $server = ContextServer::query()->findOrFail($contextServer);

        abort_unless($this->canManageContextServer($actor, $server), 403, 'Not authorized to delete this context server.');

        $server->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array{0: string, 1: Model}
     */
    protected function resolveContextTarget(string $contextType, mixed $contextId, User $actor): array
    {
        $resolvedType = strtolower(trim($contextType));

        if ($resolvedType === 'user') {
            if ($contextId === null || $contextId === '' || $contextId === 'me') {
                return ['user', $actor];
            }

            $target = User::query()->where('uuid', $contextId)->firstOrFail();
            abort_unless($actor->type === 'system' || $target->id === $actor->id, 403, 'Not authorized for this user context.');

            return ['user', $target];
        }

        if ($resolvedType === 'channel') {
            abort_if(! is_string($contextId) || trim($contextId) === '', 422, 'context_id is required for channel context.');

            $channel = Channel::query()->where('uuid', $contextId)->firstOrFail();
            Gate::forUser($actor)->authorize('view', $channel);

            return ['channel', $channel];
        }

        if ($resolvedType === 'thread') {
            abort_if(! is_string($contextId) || trim($contextId) === '', 422, 'context_id is required for thread context.');

            $thread = Thread::query()->where('uuid', $contextId)->firstOrFail();
            Gate::forUser($actor)->authorize('view', $thread);

            return ['thread', $thread];
        }

        abort(422, 'Unsupported context type.');
    }

    protected function canManageContextServer(User $actor, ContextServer $contextServer): bool
    {
        $context = $contextServer->contextable;

        if (! $context instanceof Model) {
            return false;
        }

        if ($context instanceof User) {
            return $actor->type === 'system' || $context->id === $actor->id;
        }

        if ($context instanceof Channel) {
            return Gate::forUser($actor)->check('view', $context);
        }

        if ($context instanceof Thread) {
            return Gate::forUser($actor)->check('view', $context);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapContextServer(ContextServer $contextServer): array
    {
        $context = $contextServer->contextable;
        $contextType = $context instanceof Model ? strtolower(class_basename($context)) : null;

        return [
            'id' => $contextServer->id,
            'server' => $contextServer->server,
            'label' => $contextServer->label,
            'enabled' => (bool) $contextServer->enabled,
            'priority' => $contextServer->priority,
            'transport' => $contextServer->transport,
            'endpoint_url' => $contextServer->endpoint_url,
            'handler' => $contextServer->handler,
            'allowed_tools' => is_array($contextServer->allowed_tools) ? $contextServer->allowed_tools : [],
            'auth_type' => $contextServer->auth_type,
            'context' => [
                'type' => $contextType,
                'id' => $context instanceof Model ? $this->publicIdentifier($context) : null,
            ],
            'created_at' => optional($contextServer->created_at)?->toIso8601String(),
            'updated_at' => optional($contextServer->updated_at)?->toIso8601String(),
        ];
    }

    protected function publicIdentifier(Model $model): mixed
    {
        $uuid = $model->getAttribute('uuid');

        if (is_string($uuid) && $uuid !== '') {
            return $uuid;
        }

        return $model->getKey();
    }
}
