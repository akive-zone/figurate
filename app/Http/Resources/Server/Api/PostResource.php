<?php

namespace App\Http\Resources\Server\Api;

use App\Events\Server\Api\PreparingResourcePayload;
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
        $postable = $post->postable;
        $thread = $postable instanceof Thread ? $postable : null;
        $space = match (true) {
            $postable instanceof Space => $postable,
            $thread?->threadable instanceof Space => $thread->threadable,
            default => null,
        };

        $payload = [
            'id' => $post->ulid,
            'type' => $post->type,
            'tag' => $post->tag,
            'status' => $post->status,
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

        $actor = $request->user();
        if ($actor instanceof User) {
            $event = new PreparingResourcePayload($post, $actor, $payload);
            event($event);

            return $event->payload;
        }

        return $payload;
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
