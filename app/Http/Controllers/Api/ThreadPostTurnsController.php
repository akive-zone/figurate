<?php

namespace App\Http\Controllers\Api;

use App\Features\Actions\Conversation\ProjectAgentTurns;
use App\Http\Controllers\Controller;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ThreadPostTurnsController extends Controller
{
    public function __construct(
        protected ProjectAgentTurns $projectAgentTurns,
    ) {}

    public function __invoke(Request $request, string $thread, Post $message): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $threadRecord = Thread::query()
            ->where('uuid', $thread)
            ->firstOrFail();

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
            ->filter(fn (array $turn): bool => (int) ($turn['prompt_message_id'] ?? 0) === (int) $message->id)
            ->values()
            ->all();

        return response()->json([
            'data' => $turns,
            'thread' => $threadRecord->uuid,
            'message_id' => $message->id,
        ]);
    }
}
