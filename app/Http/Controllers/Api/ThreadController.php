<?php

namespace App\Http\Controllers\Api;

use App\Events\Server\Api\PreparingResourcePayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Post\ListContextPostsRequest;
use App\Http\Requests\Server\Post\StoreContextPostRequest;
use App\Http\Requests\Server\Thread\StoreThreadRequest;
use App\Http\Resources\Server\Api\PostResource;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Support\Graph\GraphNodeService;
use App\Support\Graph\NodeFormer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ThreadController extends Controller
{
    public function store(
        StoreThreadRequest $request,
        NodeFormer $nodeFormer,
        GraphNodeService $graphNodes,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $result = $nodeFormer->form($actor, [
            'type' => 'thread',
            ...$request->validated(),
        ]);

        return response()->json([
            'data' => $graphNodes->map($result['node'], $actor),
            'relations' => $result['relations'],
        ], 201);
    }

    public function show(
        Request $request,
        string $thread,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $threadRecord = Thread::query()
            ->where('uuid', $thread)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $threadRecord);

        $spaceRecord = $threadRecord->threadable instanceof Space
            ? $threadRecord->threadable
            : null;

        if ($spaceRecord instanceof Space) {
            Gate::forUser($actor)->authorize('view', $spaceRecord);
        }

        $threadMessages = $threadRecord->messages()
            ->orderBy('created_at')
            ->get();

        $messages = $threadMessages
            ->map(function (Post $message) use ($threadRecord): array {
                return [
                    'kind' => 'message',
                    'scope' => 'thread',
                    'thread_id' => $threadRecord->uuid,
                    'id' => $message->ulid,
                    'sender_name' => null,
                    'source' => data_get($message->meta, 'source'),
                    'content' => $this->messageContent($message),
                    'created_at' => optional($message->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $payload = [
            'data' => $messages,
            'space' => $spaceRecord ? [
                'id' => $spaceRecord->uuid,
                'status' => $spaceRecord->status,
            ] : null,
            'thread' => [
                'id' => $threadRecord->uuid,
                'space_id' => $spaceRecord?->uuid,
                'purpose' => $threadRecord->purpose,
                'phase' => $threadRecord->phase,
                'status' => $threadRecord->status,
            ],
        ];
        $event = new PreparingResourcePayload($threadRecord, $actor, $payload);
        event($event);

        return response()->json($event->payload);
    }

    public function posts(ListContextPostsRequest $request, string $thread): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $threadRecord = Thread::query()
            ->where('uuid', $thread)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $threadRecord);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));
        $paginator = $threadRecord->posts()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->query('cursor'));

        return response()->json([
            'data' => PostResource::collection(
                collect($paginator->items()),
            )->toArray($request),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function storePost(
        StoreContextPostRequest $request,
        string $thread,
        NodeFormer $nodeFormer,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $threadRecord = Thread::query()
            ->where('uuid', $thread)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $threadRecord);

        $result = $nodeFormer->form($actor, [
            'type' => 'post',
            'parent' => ['type' => 'thread', 'id' => $threadRecord->uuid],
            'attributes' => $this->contextPostAttributes($request),
        ]);

        return response()->json([
            'data' => PostResource::make($result['node']),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageContent(Post $message): array
    {
        return [
            'text' => is_string($message->text) ? $message->text : '',
            'attachments' => is_array($message->attachments) ? $message->attachments : [],
            'actions' => is_array($message->actions) ? $message->actions : [],
            'errors' => is_array($message->errors) ? $message->errors : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function contextPostAttributes(StoreContextPostRequest $request): array
    {
        $validated = $request->validated();
        $input = $request->all();
        $hasPayload = array_key_exists('payload', $input);

        $payload = $hasPayload && is_array($input['payload'])
            ? $input['payload']
            : collect($input)->except([
                'type',
                'tag',
                'status',
                'text',
                'payload',
                'meta',
                'occurred_at',
            ])->all();

        return [
            'post_type' => (string) ($validated['type'] ?? 'context'),
            'tag' => is_string($validated['tag'] ?? null) ? $validated['tag'] : null,
            'status' => is_string($validated['status'] ?? null) ? $validated['status'] : Post::StatusActive,
            'text' => is_string($validated['text'] ?? null) ? $validated['text'] : null,
            'payload' => is_array($payload) ? $payload : [],
            'meta' => is_array($validated['meta'] ?? null) ? $validated['meta'] : [],
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ];
    }
}
