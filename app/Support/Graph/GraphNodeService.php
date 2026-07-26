<?php

namespace App\Support\Graph;

use App\Features\Actions\Chat\ResolveNodeInvocation;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class GraphNodeService
{
    public function __construct(protected ResolveNodeInvocation $resolveNodeInvocation) {}

    public function resolve(User $actor, string $type, string $nodeId, bool $forUpdate = false): Model
    {
        $node = match ($type) {
            'space' => Space::query()->where('uuid', $nodeId)->firstOrFail(),
            'thread' => Thread::query()->where('uuid', $nodeId)->firstOrFail(),
            'post' => Post::query()->where('ulid', $nodeId)->firstOrFail(),
            default => abort(422, 'Unsupported graph node type.'),
        };

        Gate::forUser($actor)->authorize($forUpdate ? 'update' : 'view', $node);

        return $node;
    }

    /**
     * @return Collection<int, Model>
     */
    public function children(User $actor, Space|Thread|Post $parent): Collection
    {
        $children = match (true) {
            $parent instanceof Space => $this->spaceChildren($actor, $parent),
            $parent instanceof Thread => collect()
                ->concat($parent->threads()->get())
                ->concat($parent->posts()->get()),
            $parent instanceof Post => $parent->posts()->get(),
        };

        return $children
            ->sortBy(fn (Model $node): string => sprintf(
                '%s:%020d',
                optional($node->created_at)?->toIso8601String() ?? '',
                (int) $node->getKey(),
            ))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function map(Model $node, ?User $actor = null): array
    {
        return match (true) {
            $node instanceof Space => [
                'type' => 'space',
                'id' => $node->uuid,
                'invocation' => $actor ? $this->resolveNodeInvocation->execute($actor, $node) : null,
                'attributes' => [
                    'status' => $node->status,
                    'space_count' => $this->childSpacesQuery($node)->count(),
                    'thread_count' => $node->threads()->count(),
                    'post_count' => $node->posts()->count(),
                ],
                'created_at' => optional($node->created_at)?->toIso8601String(),
            ],
            $node instanceof Thread => [
                'type' => 'thread',
                'id' => $node->uuid,
                'invocation' => $actor ? $this->resolveNodeInvocation->execute($actor, $node) : null,
                'attributes' => [
                    'title' => $node->title ?: 'Thread',
                    'purpose' => $node->purpose,
                    'phase' => $node->phase,
                    'status' => $node->status,
                    'thread_count' => $node->threads()->count(),
                    'post_count' => $node->posts()->count(),
                ],
                'created_at' => optional($node->created_at)?->toIso8601String(),
            ],
            $node instanceof Post => [
                'type' => 'post',
                'id' => $node->ulid,
                'invocation' => $actor ? $this->resolveNodeInvocation->execute($actor, $node) : null,
                'attributes' => [
                    'post_type' => $node->type,
                    'tag' => $node->tag,
                    'status' => $node->status,
                    'text' => $node->text,
                    'payload' => $node->payload ?? [],
                    'meta' => $node->meta ?? [],
                    'occurred_at' => optional($node->occurred_at)?->toIso8601String(),
                    'post_count' => $node->posts()->count(),
                ],
                'created_at' => optional($node->created_at)?->toIso8601String(),
            ],
            default => abort(422, 'Unsupported graph node model.'),
        };
    }

    /**
     * @return array{type: 'space'|'thread'|'post', id: string}
     */
    public function reference(Space|Thread|Post $node): array
    {
        return [
            'type' => match (true) {
                $node instanceof Space => 'space',
                $node instanceof Thread => 'thread',
                $node instanceof Post => 'post',
            },
            'id' => $node instanceof Post ? $node->ulid : $node->uuid,
        ];
    }

    /**
     * @return Collection<int, Model>
     */
    protected function spaceChildren(User $actor, Space $space): Collection
    {
        $childSpaces = $this->childSpacesQuery($space)
            ->get()
            ->pluck('space')
            ->filter(fn (mixed $node): bool => $node instanceof Space)
            ->filter(fn (Space $node): bool => Gate::forUser($actor)->allows('view', $node));

        return collect()
            ->concat($childSpaces)
            ->concat($space->threads()->get())
            ->concat($space->posts()->get())
            ->filter(fn (mixed $node): bool => $node instanceof Model);
    }

    protected function childSpacesQuery(Space $space): Builder
    {
        return SpaceRelation::query()
            ->with('space')
            ->where('type', SpaceRelation::TypeChildOf)
            ->whereMorphedTo('relationable', $space);
    }
}
