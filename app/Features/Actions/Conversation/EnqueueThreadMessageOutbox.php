<?php

namespace App\Features\Actions\Conversation;

use App\Jobs\DeliverOutboxMessage;
use App\Models\Server\Message;
use App\Models\Server\Outbox;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Support\Collection;

class EnqueueThreadMessageOutbox
{
    /**
     * @return Collection<int, Outbox>
     */
    public function execute(Message $message): Collection
    {
        $thread = $this->resolveThread($message);
        if (! $thread) {
            return collect();
        }

        if (! $this->shouldFanOut($message)) {
            return collect();
        }

        $created = collect();

        foreach ($this->resolveFanoutTargets($thread) as $target) {
            $idempotencyKey = sprintf(
                'outbound:%d:%s:%s:%s',
                $message->id,
                $target['protocol'],
                $target['provider'] ?? 'default',
                sha1($target['target'])
            );

            $outbox = Outbox::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'thread_id' => $thread->id,
                    'message_id' => $message->id,
                    'direction' => Outbox::DirectionOutbound,
                    'protocol' => $target['protocol'],
                    'provider' => $target['provider'],
                    'target' => $target['target'],
                    'status' => Outbox::StatusPending,
                    'attempts' => 0,
                    'available_at' => now(),
                    'payload' => [
                        'message' => [
                            'id' => $message->id,
                            'text' => $message->text,
                            'source' => data_get($message->meta, 'source'),
                            'meta' => is_array($message->meta) ? $message->meta : [],
                            'created_at' => optional($message->created_at)?->toIso8601String(),
                        ],
                        'delivery' => [
                            'provider' => $target['provider'],
                        ],
                        'thread' => [
                            'id' => $thread->id,
                            'uuid' => $thread->uuid,
                        ],
                    ],
                ],
            );

            if ($outbox->wasRecentlyCreated) {
                DeliverOutboxMessage::dispatch($outbox->id);
                $created->push($outbox);
            }
        }

        return $created;
    }

    protected function resolveThread(Message $message): ?Thread
    {
        $threadMorphClass = (new Thread)->getMorphClass();
        $messageableType = is_string($message->messageable_type) ? trim($message->messageable_type) : '';

        if (! in_array($messageableType, [$threadMorphClass, Thread::class], true)) {
            return null;
        }

        return Thread::query()->find($message->messageable_id);
    }

    protected function shouldFanOut(Message $message): bool
    {
        $source = (string) data_get($message->meta, 'source', '');

        return $source !== '' && ! str_ends_with($source, '_inbound');
    }

    /**
     * @return list<array{protocol: string, provider: string|null, target: string}>
     */
    protected function resolveFanoutTargets(Thread $thread): array
    {
        return $thread->actors()
            ->whereIn('role', [ThreadActor::RoleMember, ThreadActor::RoleListener])
            ->where('status', ThreadActor::StatusActive)
            ->get()
            ->flatMap(function (ThreadActor $actor): array {
                if ($actor->actorable_type === User::class && $actor->actorable_id !== null) {
                    return [];
                }

                $config = is_array($actor->config) ? $actor->config : [];
                $targets = data_get($config, 'outbox.targets');

                if (! is_array($targets)) {
                    $singleProtocol = $this->normalizedProtocol(data_get($config, 'outbox.protocol') ?? data_get($config, 'protocol'));
                    $singleProvider = $this->normalizedProvider(data_get($config, 'outbox.provider') ?? data_get($config, 'provider'));
                    $singleTarget = $this->normalizedTarget(data_get($config, 'outbox.target') ?? data_get($config, 'target'));

                    if ($singleProtocol === null || $singleTarget === null) {
                        return [];
                    }

                    return [[
                        'protocol' => $singleProtocol,
                        'provider' => $singleProvider,
                        'target' => $singleTarget,
                    ]];
                }

                return collect($targets)
                    ->map(function (mixed $target): ?array {
                        if (! is_array($target)) {
                            return null;
                        }

                        if (($target['enabled'] ?? true) === false) {
                            return null;
                        }

                        $protocol = $this->normalizedProtocol($target['protocol'] ?? null);
                        $provider = $this->normalizedProvider($target['provider'] ?? null);
                        $endpoint = $this->normalizedTarget($target['target'] ?? null);

                        if ($protocol === null || $endpoint === null) {
                            return null;
                        }

                        return [
                            'protocol' => $protocol,
                            'provider' => $provider,
                            'target' => $endpoint,
                        ];
                    })
                    ->filter(fn (mixed $target): bool => is_array($target))
                    ->values()
                    ->all();
            })
            ->unique(fn (array $target): string => "{$target['protocol']}:".($target['provider'] ?? 'default').":{$target['target']}")
            ->values()
            ->all();
    }

    protected function normalizedProtocol(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $protocol = strtolower(trim($value));

        if ($protocol === '') {
            return null;
        }

        return $protocol;
    }

    protected function normalizedTarget(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $target = trim($value);

        if ($target === '') {
            return null;
        }

        return $target;
    }

    protected function normalizedProvider(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $provider = trim($value);

        if ($provider === '') {
            return null;
        }

        return $provider;
    }
}
