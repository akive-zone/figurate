<?php

namespace App\Support\Orchestrate;

use App\Ai\Support\ChatAgentExecutor;
use App\Features\Actions\Chat\DispatchThreadMessage;
use App\Features\Actions\Chat\ThreadMessageEntry;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Support\Collection;

class PromptDispatchService
{
    public function __construct(
        protected DispatchThreadMessage $dispatchThreadMessage,
        protected ChatAgentExecutor $chatAgentExecutor,
    ) {}

    /**
     * @param  array{
     *     direct_source?: string,
     *     agent_source?: string,
     *     dispatch_observers_when_direct?: bool,
     *     dispatch_observers_when_agent?: bool,
     *     ensure_membership?: bool,
     *     ensure_presenter?: bool,
     *     presenter_actor_type?: string|null,
     *     meta?: array<string, mixed>,
     *     actions?: array<int, array<string, mixed>>|null,
     *     errors?: array<int, array<string, mixed>>|null,
     *     broadcast_channel_id?: string|null
     * }  $options
     * @return array{
     *     message: Message,
     *     presenters: Collection<int, ThreadActor>,
     *     direct: bool,
     *     broadcast_channel_id: string
     * }
     */
    public function dispatch(Channel $channel, Thread $thread, User $actor, string $text, array $options = []): array
    {
        if (($options['ensure_membership'] ?? false) === true) {
            $this->ensureThreadMembership($thread, $actor);
        }

        $presenters = $this->resolveActivePresenters($thread);
        if (($options['ensure_presenter'] ?? false) === true && $presenters->isEmpty()) {
            $presenterActorType = $options['presenter_actor_type'] ?? null;
            if (is_string($presenterActorType) && $presenterActorType !== '') {
                $this->ensurePresenter($thread, $presenterActorType);
                $presenters = $this->resolveActivePresenters($thread);
            }
        }

        $direct = $presenters->isEmpty();
        $source = $direct
            ? (is_string($options['direct_source'] ?? null) ? $options['direct_source'] : 'peer_message')
            : (is_string($options['agent_source'] ?? null) ? $options['agent_source'] : 'agent_prompt');
        $dispatchObservers = $direct
            ? (bool) ($options['dispatch_observers_when_direct'] ?? true)
            : (bool) ($options['dispatch_observers_when_agent'] ?? false);
        $broadcastChannelId = is_string($options['broadcast_channel_id'] ?? null) && $options['broadcast_channel_id'] !== ''
            ? $options['broadcast_channel_id']
            : "threads.{$thread->uuid}";

        $message = $this->dispatchThreadMessage->execute(ThreadMessageEntry::peerMessage(
            channel: $channel,
            thread: $thread,
            actor: $actor,
            text: $text,
            attachments: collect(),
            source: $source,
            dispatchObservers: $dispatchObservers,
            meta: is_array($options['meta'] ?? null) ? $options['meta'] : [],
        ));

        $updates = [];
        if (is_array($options['actions'] ?? null) && $options['actions'] !== []) {
            $updates['actions'] = $options['actions'];
        }
        if (is_array($options['errors'] ?? null) && $options['errors'] !== []) {
            $updates['errors'] = $options['errors'];
        }

        if ($updates !== []) {
            $message->forceFill($updates)->save();
        }

        if (! $direct) {
            $presenters->each(function (ThreadActor $presenter) use ($thread, $message, $actor, $broadcastChannelId): void {
                $this->chatAgentExecutor->queue(
                    thread: $thread,
                    userMessage: $message,
                    user: $actor,
                    threadActor: $presenter,
                    broadcastChannelId: $broadcastChannelId,
                );
            });
        }

        return [
            'message' => $message,
            'presenters' => $presenters,
            'direct' => $direct,
            'broadcast_channel_id' => $broadcastChannelId,
        ];
    }

    public function ensureThreadMembership(Thread $thread, User $actor): void
    {
        $thread->actors()->firstOrCreate(
            [
                'actorable_type' => $actor->getMorphClass(),
                'actorable_id' => $actor->getKey(),
                'role' => ThreadActor::RoleMember,
            ],
            [
                'status' => ThreadActor::StatusActive,
                'priority' => 99,
                'config' => null,
            ],
        );
    }

    public function ensurePresenter(Thread $thread, string $presenterActorType): void
    {
        if ($this->resolveActivePresenters($thread)->isNotEmpty()) {
            return;
        }

        $thread->actors()->create([
            'actorable_type' => $presenterActorType,
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);
    }

    /**
     * @return Collection<int, ThreadActor>
     */
    public function resolveActivePresenters(Thread $thread): Collection
    {
        return $thread->actors()
            ->where('role', ThreadActor::RolePresenter)
            ->where('status', ThreadActor::StatusActive)
            ->orderBy('priority')
            ->get();
    }
}
