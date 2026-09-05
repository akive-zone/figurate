<?php

namespace App\Support\Graph;

use App\Events\Server\Api\PreparingResourcePayload;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;

class GraphNodeService
{
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
     * @return array{
     *     nodes: Collection<int, Model>,
     *     meta: array{count: int, per_page: int, has_more: bool, next_cursor: ?string}
     * }
     */
    public function paginateChildren(
        User $actor,
        Space|Thread|Post $parent,
        ?string $cursor = null,
        int $perPage = 25,
        ?string $type = null,
    ): array {
        $children = $this->children($actor, $parent);

        if (is_string($type) && $type !== '') {
            $children = $children
                ->filter(fn (Model $node): bool => match ($type) {
                    'space' => $node instanceof Space,
                    'thread' => $node instanceof Thread,
                    'post' => $node instanceof Post,
                    default => false,
                })
                ->values();
        }

        $perPage = max(1, min(100, $perPage));
        $offset = 0;

        if (is_string($cursor) && trim($cursor) !== '') {
            try {
                $cursorKey = Crypt::decryptString($cursor);
            } catch (DecryptException) {
                abort(422, 'The node cursor is invalid.');
            }

            $position = $children->search(
                fn (Model $node): bool => $this->nodeCursorKey($node) === $cursorKey,
            );
            abort_if($position === false, 422, 'The node cursor is no longer valid.');
            $offset = (int) $position + 1;
        }

        $page = $children->slice($offset, $perPage)->values();
        $hasMore = $offset + $page->count() < $children->count();
        $last = $page->last();

        return [
            'nodes' => $page,
            'meta' => [
                'count' => $page->count(),
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'next_cursor' => $hasMore && $last instanceof Model
                    ? Crypt::encryptString($this->nodeCursorKey($last))
                    : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function map(Model $node, ?User $actor = null): array
    {
        $payload = match (true) {
            $node instanceof Space => [
                'type' => 'space',
                'id' => $node->uuid,
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

        if (! $actor instanceof User) {
            return $payload;
        }

        $event = new PreparingResourcePayload($node, $actor, $payload);
        event($event);

        return $event->payload;
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

    protected function nodeCursorKey(Model $node): string
    {
        return sprintf('%s:%s', $node->getMorphClass(), $node->getKey());
    }
}
