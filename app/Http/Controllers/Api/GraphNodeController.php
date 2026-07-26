<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Graph\StoreGraphNodeRequest;
use App\Http\Requests\Server\Graph\UpdateGraphNodeRequest;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use App\Support\Graph\GraphNodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GraphNodeController extends Controller
{
    public function __construct(protected GraphNodeService $graphNodes) {}

    public function show(Request $request, string $type, string $node): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $resolvedNode = $this->graphNodes->resolve($actor, $type, $node);

        return response()->json([
            'data' => $this->graphNodes->map($resolvedNode, $actor),
        ]);
    }

    public function store(StoreGraphNodeRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $attributes = is_array($validated['attributes'] ?? null) ? $validated['attributes'] : [];
        $parent = $this->resolveParent($actor, $validated['parent'] ?? null);

        $node = DB::transaction(fn (): Model => match ($validated['type']) {
            'space' => $this->createSpace($actor, $parent, $attributes),
            'thread' => $this->createThread($actor, $parent, $attributes),
            'post' => $this->createPost($actor, $parent, $attributes),
        });

        return response()->json([
            'data' => $this->graphNodes->map($node, $actor),
        ], 201);
    }

    public function update(UpdateGraphNodeRequest $request, string $type, string $node): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $resolvedNode = $this->graphNodes->resolve($actor, $type, $node, true);
        $attributes = $request->validated('attributes');
        abort_unless(is_array($attributes), 422);

        DB::transaction(function () use ($attributes, $resolvedNode): void {
            match (true) {
                $resolvedNode instanceof Space => $resolvedNode->fill([
                    'status' => $attributes['status'] ?? $resolvedNode->status,
                ])->save(),
                $resolvedNode instanceof Thread => $resolvedNode->fill(
                    collect($attributes)
                        ->only(['title', 'purpose', 'phase', 'status'])
                        ->all(),
                )->save(),
                $resolvedNode instanceof Post => $this->updatePost($resolvedNode, $attributes),
                default => abort(422, 'Unsupported graph node model.'),
            };
        });

        return response()->json([
            'data' => $this->graphNodes->map($resolvedNode->refresh(), $actor),
        ]);
    }

    public function destroy(Request $request, string $type, string $node): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $resolvedNode = $this->graphNodes->resolve($actor, $type, $node);
        Gate::forUser($actor)->authorize('delete', $resolvedNode);

        abort_if(
            $this->graphNodes->children($actor, $resolvedNode)->isNotEmpty(),
            409,
            'A node with child nodes cannot be deleted.',
        );

        DB::transaction(function () use ($resolvedNode): void {
            if (method_exists($resolvedNode, 'relations')) {
                $resolvedNode->relations()->delete();
            }

            foreach (['inboundSpaceRelations', 'inboundThreadRelations', 'inboundPostRelations'] as $method) {
                if (method_exists($resolvedNode, $method)) {
                    $resolvedNode->{$method}()->each->delete();
                }
            }

            $resolvedNode->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function updatePost(Post $post, array $attributes): void
    {
        $values = collect($attributes)
            ->only(['tag', 'status', 'meta', 'occurred_at'])
            ->all();

        if (array_key_exists('post_type', $attributes)) {
            $values['type'] = $attributes['post_type'];
        }

        if (array_key_exists('payload', $attributes) || array_key_exists('text', $attributes)) {
            $payload = array_key_exists('payload', $attributes) && is_array($attributes['payload'])
                ? $attributes['payload']
                : ($post->payload ?? []);

            if (array_key_exists('text', $attributes)) {
                if ($attributes['text'] === null) {
                    unset($payload['text']);
                } else {
                    $payload['text'] = $attributes['text'];
                }
            }

            $values['payload'] = $payload;
        }

        $post->fill($values)->save();
    }

    /**
     * @param  array<string, mixed>|null  $parent
     */
    protected function resolveParent(User $actor, ?array $parent): Space|Thread|Post|null
    {
        if (! is_array($parent)) {
            return null;
        }

        $resolved = $this->graphNodes->resolve(
            $actor,
            (string) ($parent['type'] ?? ''),
            (string) ($parent['id'] ?? ''),
            true,
        );

        abort_unless($resolved instanceof Space || $resolved instanceof Thread || $resolved instanceof Post, 422);

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createSpace(User $actor, Space|Thread|Post|null $parent, array $attributes): Space
    {
        Gate::forUser($actor)->authorize('create', Space::class);
        abort_if($parent instanceof Thread || $parent instanceof Post, 422, 'A Space node may only be contained by another Space.');

        $space = Space::query()->create([
            'status' => (string) ($attributes['status'] ?? 'open'),
        ]);
        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $actor->getMorphClass(),
            'actorable_id' => $actor->getKey(),
            'status' => SpaceActorState::StatusActive,
        ]);

        if ($parent instanceof Space) {
            $space->attachRelation($parent, SpaceRelation::TypeChildOf);
        }

        return $space;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createThread(User $actor, Space|Thread|Post|null $parent, array $attributes): Thread
    {
        Gate::forUser($actor)->authorize('create', Thread::class);
        abort_unless($parent instanceof Space || $parent instanceof Thread, 422, 'A Thread node must be contained by a Space or Thread.');

        $thread = $parent->threads()->create([
            'title' => (string) $attributes['title'],
            'purpose' => (string) ($attributes['purpose'] ?? Thread::PurposeMain),
            'phase' => (string) ($attributes['phase'] ?? Thread::PhaseInitial),
            'status' => (string) ($attributes['status'] ?? 'open'),
        ]);
        $thread->actors()->create([
            'actorable_type' => $actor->getMorphClass(),
            'actorable_id' => $actor->getKey(),
            'role' => ThreadActor::RoleMember,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        return $thread;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createPost(User $actor, Space|Thread|Post|null $parent, array $attributes): Post
    {
        Gate::forUser($actor)->authorize('create', Post::class);
        abort_unless(
            $parent instanceof Space || $parent instanceof Thread || $parent instanceof Post,
            422,
            'A Post node requires a Space, Thread, or Post parent.',
        );

        $payload = is_array($attributes['payload'] ?? null) ? $attributes['payload'] : [];
        if (is_string($attributes['text'] ?? null)) {
            $payload['text'] = $attributes['text'];
        }

        $post = $parent->posts()->create([
            'type' => (string) ($attributes['post_type'] ?? Post::TypeMessage),
            'tag' => is_string($attributes['tag'] ?? null) ? $attributes['tag'] : null,
            'status' => (string) ($attributes['status'] ?? Post::StatusActive),
            'payload' => $payload,
            'meta' => is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [],
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
        $post->attachRelation($actor, Post::RelationRoleSender);

        return $post;
    }
}
