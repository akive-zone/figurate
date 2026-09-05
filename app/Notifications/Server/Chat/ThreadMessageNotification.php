<?php

namespace App\Notifications\Server\Chat;

use App\Events\Server\Notifications\RoutingNotificationChannels;
use App\Models\Server\Inbox;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Notifications\Channels\CoordinationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ThreadMessageNotification extends Notification
{
    use Queueable;

    public function __construct(protected Post $post) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        if (! app()->bound('events')) {
            return [CoordinationChannel::class];
        }

        $event = new RoutingNotificationChannels(
            notifiable: $notifiable,
            notification: $this,
            channels: [CoordinationChannel::class],
        );
        event($event);

        return $event->channels;
    }

    public function toInbox(object $notifiable): ?Post
    {
        return $notifiable instanceof User ? $this->post : null;
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
        $text = is_string($this->post->text) ? trim($this->post->text) : null;

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
                'id' => $this->post->id,
                'ulid' => $this->post->ulid,
                'type' => $this->post->type,
                'text' => $text !== '' ? $text : null,
                'source' => $source,
                'senderable_type' => $this->post->senderable_type,
                'senderable_id' => $this->post->senderable_id,
                'attachments_count' => count(is_array($this->post->attachments) ? $this->post->attachments : []),
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
        if ($this->post->relationLoaded('postable') && $this->post->postable instanceof Thread) {
            return $this->post->postable;
        }

        $threadMorphClass = (new Thread)->getMorphClass();
        $postableType = is_string($this->post->postable_type) ? trim($this->post->postable_type) : '';

        if (! in_array($postableType, [$threadMorphClass, Thread::class], true)) {
            return null;
        }

        return Thread::query()->find($this->post->postable_id);
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
        $source = data_get($this->post->meta, 'source');

        return is_string($source) && trim($source) !== ''
            ? trim($source)
            : 'thread_message';
    }

    protected function fallbackTitle(string $source): string
    {
        return match (true) {
            $source === 'peer_message' => 'New message',
            str_ends_with($source, '_inbound') => 'External update',
            default => 'Conversation update',
        };
    }

    protected function fallbackSummary(?string $text): ?string
    {
        if (is_string($text) && $text !== '') {
            return mb_substr($text, 0, 240);
        }

        $attachmentCount = count(is_array($this->post->attachments) ? $this->post->attachments : []);

        if ($attachmentCount > 0) {
            return $attachmentCount === 1 ? '1 attachment' : "{$attachmentCount} attachments";
        }

        return null;
    }
}
