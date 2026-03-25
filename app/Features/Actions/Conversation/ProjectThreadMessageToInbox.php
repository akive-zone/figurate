<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Inbox;
use App\Models\Server\Post;
use App\Models\Server\User;

class ProjectThreadMessageToInbox extends ProjectInbox
{
    public function execute(User $user, Post $post): ?Inbox
    {
        return $this->project(
            $user,
            $post,
            Inbox::KindThreadMessage,
            $this->titleFor($post),
            $this->summaryFor($post),
            $this->sourceFor($post),
            $this->payloadFor($post),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadFor(Post $post): array
    {
        return [
            'message_ulid' => $post->ulid,
            'message_type' => data_get($post->data, 'message_type', 'text'),
            'senderable_type' => $post->senderable_type,
            'senderable_id' => $post->senderable_id,
            'has_attachments' => ! empty($post->attachments),
            'attachments_count' => count(is_array($post->attachments) ? $post->attachments : []),
        ];
    }

    protected function titleFor(Post $post): string
    {
        $source = $this->sourceFor($post);

        return match (true) {
            $source === 'peer_message' => 'New message',
            str_ends_with($source, '_inbound') => 'External update',
            str_contains($source, 'agent') || str_contains($source, 'observer') => 'Agent update',
            default => 'Conversation update',
        };
    }

    protected function summaryFor(Post $post): ?string
    {
        $text = is_string($post->text) ? trim($post->text) : '';

        if ($text !== '') {
            return mb_substr($text, 0, 240);
        }

        $attachmentCount = count(is_array($post->attachments) ? $post->attachments : []);

        if ($attachmentCount > 0) {
            return $attachmentCount === 1 ? '1 attachment' : "{$attachmentCount} attachments";
        }

        return null;
    }

    protected function sourceFor(Post $post): string
    {
        $source = data_get($post->meta, 'source');

        if (! is_string($source) || trim($source) === '') {
            return 'thread_message';
        }

        return trim($source);
    }
}
