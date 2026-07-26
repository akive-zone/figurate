<?php

namespace App\Http\Controllers\Api;

use App\Features\Actions\Chat\ProjectAgentTurns;
use App\Http\Controllers\Controller;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostTurnsController extends Controller
{
    public function __construct(
        protected ProjectAgentTurns $projectAgentTurns,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $message = $this->resolvePost((string) $request->route('post'));
        $threadRecord = $request->route('thread')
            ? Thread::query()->where('uuid', (string) $request->route('thread'))->firstOrFail()
            : $this->resolveThreadForPost($message);

        Gate::forUser($actor)->authorize('view', $threadRecord);

        if (
            $message->postable_type !== $threadRecord->getMorphClass()
            || $message->postable_id !== $threadRecord->getKey()
        ) {
            abort(404, 'Message not found in this thread.');
        }

        $threadMessages = $threadRecord->messages()
            ->orderBy('created_at')
            ->get();
        $turns = collect($this->projectAgentTurns->execute($threadRecord, $threadMessages, $actor))
            ->filter(fn (array $turn): bool => (int) ($turn['prompt_post_id'] ?? 0) === (int) $message->id)
            ->values()
            ->all();

        return response()->json([
            'data' => $turns,
            'thread' => $threadRecord->uuid,
            'post_id' => $message->ulid,
        ]);
    }

    protected function resolvePost(string $post): Post
    {
        return Post::query()
            ->where('ulid', $post)
            ->when(ctype_digit($post), fn ($query) => $query->orWhere('id', (int) $post))
            ->firstOrFail();
    }

    protected function resolveThreadForPost(Post $post): Thread
    {
        if ($post->postable instanceof Thread) {
            return $post->postable;
        }

        abort(404, 'Message not found in a thread.');
    }
}
