<?php

namespace App\Support\Channels\WebSocket;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;
use WebSocket\Connection;

/**
 * Inbound Message Handler
 *
 * Processes incoming WebSocket messages from clients and creates Post records.
 */
class InboundMessageHandler
{
    /**
     * Handle incoming message from WebSocket client
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(Connection $connection, array $data, Channel $channel): array
    {
        // Validate message format
        $validation = $this->validateMessage($data);
        if (! $validation['valid']) {
            return [
                'status' => 'error',
                'error' => $validation['error'],
            ];
        }

        // Extract message details
        $threadUuid = $data['thread_uuid'] ?? $data['thread'] ?? null;
        $senderIdentifier = $data['sender'] ?? $data['user_id'] ?? null;
        $messageText = $data['text'] ?? $data['message'] ?? '';
        $messageType = $data['type'] ?? 'message';

        // Resolve thread
        $thread = $this->resolveThread($threadUuid);
        if (! $thread) {
            return [
                'status' => 'error',
                'error' => 'Thread not found',
            ];
        }

        // Resolve sender
        $sender = $this->resolveSender($senderIdentifier);

        // Create post
        $post = $this->createPost($thread, $messageText, $messageType, $sender, $data);

        return [
            'status' => 'success',
            'message_id' => $data['id'] ?? null,
            'post_id' => $post->id,
            'post_ulid' => $post->ulid,
            'thread_uuid' => $thread->uuid,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, error?: string}
     */
    protected function validateMessage(array $data): array
    {
        // Check for required fields
        if (! isset($data['thread_uuid']) && ! isset($data['thread'])) {
            return ['valid' => false, 'error' => 'Missing thread identifier'];
        }

        if (! isset($data['text']) && ! isset($data['message'])) {
            return ['valid' => false, 'error' => 'Missing message text'];
        }

        return ['valid' => true];
    }

    protected function resolveThread(mixed $identifier): ?Thread
    {
        if (! is_string($identifier) || trim($identifier) === '') {
            return null;
        }

        return Thread::query()
            ->where('uuid', $identifier)
            ->first();
    }

    protected function resolveSender(mixed $identifier): ?User
    {
        if (! is_string($identifier) && ! is_int($identifier)) {
            return null;
        }

        // Try to find by UUID first
        if (is_string($identifier)) {
            $user = User::query()->where('uuid', $identifier)->first();
            if ($user) {
                return $user;
            }
        }

        // Try by ID
        return User::query()->find($identifier);
    }

    /**
     * @param  array<string, mixed>  $rawData
     */
    protected function createPost(
        Thread $thread,
        string $text,
        string $type,
        ?User $sender,
        array $rawData
    ): Post {
        return DB::transaction(function () use ($thread, $text, $type, $sender, $rawData) {
            $post = $thread->posts()->create([
                'type' => $this->normalizePostType($type),
                'status' => Post::StatusActive,
                'text' => $text,
                'data' => [
                    'text' => $text,
                    'message_type' => $type,
                ],
                'meta' => [
                    'source' => 'websocket_inbound',
                    'channel_type' => 'websocket',
                    'raw_data' => $rawData,
                ],
            ]);

            if ($sender) {
                $post->attachRelation($sender, Post::RelationRoleSender);
            }

            return $post;
        });
    }

    protected function normalizePostType(string $type): string
    {
        // Currently only TypeMessage is defined in Post model
        return Post::TypeMessage;
    }
}
