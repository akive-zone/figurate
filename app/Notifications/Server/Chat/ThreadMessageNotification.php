<?php

namespace App\Notifications\Server\Chat;

use App\Ai\Storage\ConversationPersistenceResolver;
use App\Models\Server\Inbox;
use App\Models\Server\Message;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Notifications\Support\ConversationTransportRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ThreadMessageNotification extends Notification
{
    use Queueable;

    public function __construct(protected Message $message) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return (new ConversationTransportRouter)->channelsFor($notifiable, $this);
    }

    public function toInbox(object $notifiable): ?Message
    {
        return $notifiable instanceof User ? $this->message : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toCoordination(object $notifiable): ?array
    {
        if (! $notifiable instanceof User || ! $notifiable->isRobot()) {
            return null;
        }

        return [
            'thread' => $this->resolveThread(),
            'space' => $this->resolveSpace($this->resolveThread()),
            'message' => $this->message,
            'recipient' => $notifiable,
            'source' => $this->source(),
            'conversation_persistence' => $this->conversationPersistenceMode(),
            'payload' => $this->payload(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toPromptTransport(object $notifiable): ?array
    {
        if (! $notifiable instanceof User || ! $notifiable->isRobot()) {
            return null;
        }

        return [
            'thread' => $this->resolveThread(),
            'space' => $this->resolveSpace($this->resolveThread()),
            'message' => $this->message,
            'recipient' => $notifiable,
            'source' => $this->source(),
            'conversation_persistence' => $this->conversationPersistenceMode(),
        ];
    }

    public function transportChannelName(object $notifiable): string
    {
        if (! $notifiable instanceof User || ! $notifiable->isRobot()) {
            return ConversationTransportRouter::Coordination;
        }

        return match ($this->conversationPersistenceMode()) {
            ConversationPersistenceResolver::ThreadCompletion => ConversationTransportRouter::Completion,
            ConversationPersistenceResolver::ThreadContinuation => ConversationTransportRouter::Continuation,
            default => ConversationTransportRouter::Coordination,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $thread = $this->resolveThread();
        $space = $this->resolveSpace($thread);
        $source = $this->source();
        $text = is_string($this->message->text) ? trim($this->message->text) : null;

        return [
            'kind' => Inbox::KindThreadMessage,
            'thread' => [
                'id' => $thread?->uuid,
                'title' => $thread?->title ?: 'Thread',
                'purpose' => $thread?->purpose,
                'phase' => $thread?->phase,
                'status' => $thread?->status,
            ],
            'space' => [
                'id' => $space?->uuid,
                'status' => $space?->status,
            ],
            'message' => [
                'id' => $this->message->id,
                'ulid' => $this->message->ulid,
                'type' => $this->message->type,
                'text' => $text !== '' ? $text : null,
                'source' => $source,
                'conversation_persistence' => $this->conversationPersistenceMode(),
                'senderable_type' => $this->message->senderable_type,
                'senderable_id' => $this->message->senderable_id,
                'attachments_count' => count(is_array($this->message->attachments) ? $this->message->attachments : []),
            ],
            'inbox' => [
                'id' => null,
                'title' => $this->fallbackTitle($source),
                'summary' => $this->fallbackSummary($text),
                'source' => $source,
                'status' => Inbox::StatusUnread,
            ],
        ];
    }

    protected function resolveThread(): ?Thread
    {
        if ($this->message->relationLoaded('messageable') && $this->message->messageable instanceof Thread) {
            return $this->message->messageable;
        }

        $threadMorphClass = (new Thread)->getMorphClass();
        $messageableType = is_string($this->message->messageable_type) ? trim($this->message->messageable_type) : '';

        if (! in_array($messageableType, [$threadMorphClass, Thread::class], true)) {
            return null;
        }

        return Thread::query()->find($this->message->messageable_id);
    }

    protected function resolveSpace(?Thread $thread): ?Space
    {
        if (! $thread) {
            return null;
        }

        if ($thread->relationLoaded('threadable') && $thread->threadable instanceof Space) {
            return $thread->threadable;
        }

        $threadableType = is_string($thread->threadable_type) ? trim($thread->threadable_type) : '';
        $spaceMorphClass = (new Space)->getMorphClass();

        if (! in_array($threadableType, [$spaceMorphClass, Space::class], true)) {
            return null;
        }

        return Space::query()->find($thread->threadable_id);
    }

    protected function source(): string
    {
        $source = data_get($this->message->meta, 'source');

        return is_string($source) && trim($source) !== ''
            ? trim($source)
            : 'thread_message';
    }

    protected function conversationPersistenceMode(): ?string
    {
        return ConversationPersistenceResolver::normalizeMode(
            data_get($this->message->meta, 'conversation_persistence')
        );
    }

    protected function fallbackTitle(string $source): string
    {
        return match (true) {
            $source === 'peer_message' => 'New message',
            str_ends_with($source, '_inbound') => 'External update',
            str_contains($source, 'agent') || str_contains($source, 'observer') => 'Agent update',
            default => 'Conversation update',
        };
    }

    protected function fallbackSummary(?string $text): ?string
    {
        if (is_string($text) && $text !== '') {
            return mb_substr($text, 0, 240);
        }

        $attachmentCount = count(is_array($this->message->attachments) ? $this->message->attachments : []);

        if ($attachmentCount > 0) {
            return $attachmentCount === 1 ? '1 attachment' : "{$attachmentCount} attachments";
        }

        return null;
    }
}
