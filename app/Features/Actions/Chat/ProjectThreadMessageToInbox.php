<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Inbox;
use App\Models\Server\Message;
use App\Models\Server\User;

class ProjectThreadMessageToInbox extends ProjectInbox
{
    public function execute(User $user, Message $message): ?Inbox
    {
        return $this->project(
            $user,
            $message,
            Inbox::KindThreadMessage,
            $this->titleFor($message),
            $this->summaryFor($message),
            $this->sourceFor($message),
            $this->payloadFor($message),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadFor(Message $message): array
    {
        return [
            'message_ulid' => $message->ulid,
            'message_type' => $message->type,
            'senderable_type' => $message->senderable_type,
            'senderable_id' => $message->senderable_id,
            'has_attachments' => ! empty($message->attachments),
            'attachments_count' => count(is_array($message->attachments) ? $message->attachments : []),
        ];
    }

    protected function titleFor(Message $message): string
    {
        $source = $this->sourceFor($message);

        return match (true) {
            $source === 'peer_message' => 'New message',
            str_ends_with($source, '_inbound') => 'External update',
            str_contains($source, 'agent') || str_contains($source, 'observer') => 'Agent update',
            default => 'Conversation update',
        };
    }

    protected function summaryFor(Message $message): ?string
    {
        $text = is_string($message->text) ? trim($message->text) : '';

        if ($text !== '') {
            return mb_substr($text, 0, 240);
        }

        $attachmentCount = count(is_array($message->attachments) ? $message->attachments : []);

        if ($attachmentCount > 0) {
            return $attachmentCount === 1 ? '1 attachment' : "{$attachmentCount} attachments";
        }

        return null;
    }

    protected function sourceFor(Message $message): string
    {
        $source = data_get($message->meta, 'source');

        if (! is_string($source) || trim($source) === '') {
            return 'thread_message';
        }

        return trim($source);
    }
}
