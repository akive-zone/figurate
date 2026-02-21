<?php

namespace App\Ai\Concerns;

use App\Ai\Agents\OrderAgent;
use App\Ai\Agents\RequestAgent;
use App\Models\Server\Thread;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;

trait RemembersThreadConversations
{
    protected ?string $conversationId = null;

    protected ?object $conversationUser = null;

    public function forUser($user): static
    {
        $this->conversationUser = $user;
        $this->conversationId = $this->threadConversationId();

        return $this;
    }

    public function continue(string $conversationId, object $as): static
    {
        $threadConversationId = $this->threadConversationId();
        $threadUuid = property_exists($this, 'thread') && $this->thread instanceof Thread
            ? (string) $this->thread->uuid
            : '';

        $shouldUseThreadConversationId = $threadConversationId !== null
            && $threadUuid !== ''
            && ! str_starts_with($conversationId, $threadUuid.':');

        $this->conversationId = $shouldUseThreadConversationId
            ? $threadConversationId
            : $conversationId;
        $this->conversationUser = $as;

        return $this;
    }

    public function continueLastConversation(object $as): static
    {
        $this->conversationUser = $as;

        $this->conversationId = $this->threadConversationId()
            ?? resolve(ConversationStore::class)->latestConversationId($as->id);

        return $this;
    }

    public function messages(): iterable
    {
        if (! $this->conversationId) {
            return [];
        }

        return resolve(ConversationStore::class)
            ->getLatestConversationMessages(
                $this->conversationId,
                $this->maxConversationMessages()
            )->all();
    }

    protected function maxConversationMessages(): int
    {
        return 100;
    }

    public function currentConversation(): ?string
    {
        return $this->conversationId;
    }

    public function hasConversationParticipant(): bool
    {
        return $this->conversationUser !== null;
    }

    public function conversationParticipant(): ?object
    {
        return $this->conversationUser;
    }

    protected function threadConversationId(): ?string
    {
        $thread = property_exists($this, 'thread') ? $this->thread : null;

        if (! $thread instanceof Thread || ! is_string($thread->uuid) || $thread->uuid === '') {
            return null;
        }

        return $thread->uuid.':'.$this->conversationActorKey();
    }

    protected function conversationActorKey(): string
    {
        return match ($this::class) {
            RequestAgent::class => 'request_agent',
            OrderAgent::class => 'order_agent',
            default => Str::snake(class_basename($this)),
        };
    }
}
