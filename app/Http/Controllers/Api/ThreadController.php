<?php

namespace App\Http\Controllers\Api;

use App\Features\Actions\Chat\ProjectAgentTurns;
use App\Features\Actions\Chat\ProjectMessageExtra;
use App\Http\Controllers\Controller;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ThreadController extends Controller
{
    public function __construct(
        protected ProjectMessageExtra $projectMessageExtra,
    ) {}

    public function show(
        Request $request,
        string $thread,
        ProjectAgentTurns $projectAgentTurns,
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
                    'id' => $message->id,
                    'sender_name' => null,
                    'source' => data_get($message->meta, 'source'),
                    'is_agent' => data_get($message->meta, 'source') === 'agent_response',
                    'content' => $this->messageContent($message),
                    'extra' => $this->projectMessageExtra->execute($message),
                    'created_at' => optional($message->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $turns = $projectAgentTurns->execute($threadRecord, $threadMessages, $actor);

        return response()->json([
            'data' => $messages,
            'turns' => $turns,
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
        ]);
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
}
