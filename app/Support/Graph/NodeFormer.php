<?php

namespace App\Support\Graph;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use App\Support\Channels\ChannelAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class NodeFormer
{
    public function __construct(
        protected GraphNodeService $graphNodes,
        protected ChannelAccess $channelAccess,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array{node: Model, created: bool, relations: list<array<string, mixed>>}
     */
    public function form(User $actor, array $body): array
    {
        return DB::transaction(function () use ($actor, $body): array {
            $type = (string) ($body['type'] ?? '');
            $nodeId = $body['id'] ?? null;
            $attributes = is_array($body['attributes'] ?? null) ? $body['attributes'] : [];

            if (is_string($nodeId) && $nodeId !== '') {
                $node = $this->graphNodes->resolve($actor, $type, $nodeId, true);
                $this->update($node, $attributes);
                $created = false;
            } else {
                $parent = $this->resolveNodeReference($actor, $body['parent'] ?? null, true);
                $node = $this->create($actor, $type, $parent, $attributes);
                $created = true;
            }

            $relations = $this->formRelations(
                $actor,
                $node,
                is_array($body['relations'] ?? null) ? $body['relations'] : [],
            );

            return [
                'node' => $node->refresh(),
                'created' => $created,
                'relations' => $relations,
            ];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $relations
     * @return list<array<string, mixed>>
     */
    public function formRelations(User $actor, Model $node, array $relations): array
    {
        return collect($relations)
            ->map(function (array $relation) use ($actor, $node): array {
                $target = $this->resolveTargetReference($actor, $relation['target'] ?? null);
                $role = trim((string) ($relation['role'] ?? 'related_to'));
                $purpose = is_string($relation['purpose'] ?? null)
                    ? trim($relation['purpose'])
                    : null;

                abort_if($role === '', 422, 'A relation role is required.');

                match (true) {
                    $node instanceof Space => $node->attachRelation($target, $role, $purpose),
                    $node instanceof Thread => $node->attachRelation($target, $role, $purpose),
                    $node instanceof Post => $node->relations()->firstOrCreate([
                        'relationable_type' => $target->getMorphClass(),
                        'relationable_id' => $target->getKey(),
                        'role' => $role,
                    ]),
                    default => abort(422, 'Unsupported relation source type.'),
                };

                return [
                    'role' => $role,
                    'purpose' => $purpose,
                    'target' => $this->reference($target),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function create(
        User $actor,
        string $type,
        Space|Thread|Post|null $parent,
        array $attributes,
    ): Model {
        return match ($type) {
            'space' => $this->createSpace($actor, $parent, $attributes),
            'thread' => $this->createThread($actor, $parent, $attributes),
            'post' => $this->createPost($actor, $parent, $attributes),
            default => abort(422, 'Unsupported node type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function update(Model $node, array $attributes): void
    {
        match (true) {
            $node instanceof Space => $node->fill(
                collect($attributes)->only(['status'])->all(),
            )->save(),
            $node instanceof Thread => $node->fill(
                collect($attributes)->only(['title', 'purpose', 'phase', 'status'])->all(),
            )->save(),
            $node instanceof Post => $this->updatePost($node, $attributes),
            default => abort(422, 'Unsupported node type.'),
        };
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
            'title' => (string) ($attributes['title'] ?? ''),
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

    protected function resolveNodeReference(User $actor, mixed $reference, bool $forUpdate = false): Space|Thread|Post|null
    {
        if (! is_array($reference)) {
            return null;
        }

        $node = $this->graphNodes->resolve(
            $actor,
            (string) ($reference['type'] ?? ''),
            (string) ($reference['id'] ?? ''),
            $forUpdate,
        );

        return $node instanceof Space || $node instanceof Thread || $node instanceof Post
            ? $node
            : abort(422, 'Unsupported node reference.');
    }

    protected function resolveTargetReference(User $actor, mixed $reference): Model
    {
        abort_unless(is_array($reference), 422, 'A relation target is required.');

        $type = (string) ($reference['type'] ?? '');
        $id = (string) ($reference['id'] ?? '');

        if ($type === 'channel') {
            $channel = Channel::query()->where('uuid', $id)->firstOrFail();
            abort_unless(
                $this->channelAccess->canManage($actor, $channel),
                403,
                'Not authorized to form a relation with this channel.',
            );

            return $channel;
        }

        return $this->graphNodes->resolve($actor, $type, $id);
    }

    /**
     * @return array{type: string, id: string}
     */
    protected function reference(Model $model): array
    {
        return [
            'type' => match (true) {
                $model instanceof Channel => 'channel',
                $model instanceof Space => 'space',
                $model instanceof Thread => 'thread',
                $model instanceof Post => 'post',
                default => strtolower(class_basename($model)),
            },
            'id' => (string) ($model->getAttribute('uuid') ?? $model->getAttribute('ulid') ?? $model->getKey()),
        ];
    }
}
