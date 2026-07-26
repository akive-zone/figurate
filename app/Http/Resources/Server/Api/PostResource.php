<?php

namespace App\Http\Resources\Server\Api;

use App\Features\Actions\Chat\ResolveNodeInvocation;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;
        $actor = $request->user();
        $postable = $post->postable;
        $thread = $postable instanceof Thread ? $postable : null;
        $space = match (true) {
            $postable instanceof Space => $postable,
            $thread?->threadable instanceof Space => $thread->threadable,
            default => null,
        };

        return [
            'id' => $post->ulid,
            'database_id' => $post->id,
            'type' => $post->type,
            'tag' => $post->tag,
            'status' => $post->status,
            'invocation' => $actor instanceof User
                ? app(ResolveNodeInvocation::class)->execute($actor, $post)
                : null,
            'text' => $post->text,
            'payload' => $post->payload ?? [],
            'meta' => $post->meta ?? [],
            'postable' => [
                'type' => $this->postableType($postable),
                'id' => $this->postableId($postable),
            ],
            'space' => $space ? [
                'id' => $space->uuid,
                'status' => $space->status,
            ] : null,
            'thread' => $thread ? [
                'id' => $thread->uuid,
                'space_id' => $space?->uuid,
                'purpose' => $thread->purpose,
                'phase' => $thread->phase,
                'status' => $thread->status,
            ] : null,
            'occurred_at' => optional($post->occurred_at)?->toIso8601String(),
            'created_at' => optional($post->created_at)?->toIso8601String(),
        ];
    }

    protected function postableType(mixed $postable): ?string
    {
        return match (true) {
            $postable instanceof Space => 'space',
            $postable instanceof Thread => 'thread',
            default => null,
        };
    }

    protected function postableId(mixed $postable): mixed
    {
        if ($postable instanceof Space || $postable instanceof Thread) {
            return $postable->uuid;
        }

        return null;
    }
}
