<?php

namespace App\Support\Channels\WebSocket;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Inbound Client Message Handler
 *
 * Processes incoming messages from external WebSocket servers
 * when connected as a client.
 */
class InboundClientMessageHandler
{
    /**
     * Handle incoming message from external WebSocket server
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(Channel $channel, array $data): array
    {
        Log::info('[WebSocket Client] Received message', [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'data' => $data,
        ]);

        // Validate message format
        $validation = $this->validateMessage($data);
        if (! $validation['valid']) {
            Log::warning('[WebSocket Client] Invalid message', [
                'error' => $validation['error'],
                'data' => $data,
            ]);

            return [
                'status' => 'error',
                'error' => $validation['error'],
            ];
        }

        // Extract message details
        $threadUuid = $data['thread_uuid'] ?? $data['thread'] ?? null;
        $senderIdentifier = $data['sender'] ?? $data['user_id'] ?? $data['from'] ?? null;
        $messageText = $data['text'] ?? $data['message'] ?? $data['content'] ?? '';
        $messageType = $data['type'] ?? $data['event'] ?? 'message';

        // Resolve thread - try to find by UUID, or use a default thread for the channel
        $thread = $this->resolveThread($channel, $threadUuid);
        if (! $thread) {
            Log::warning('[WebSocket Client] Thread not found', [
                'thread_uuid' => $threadUuid,
                'channel_id' => $channel->id,
            ]);

            return [
                'status' => 'error',
                'error' => 'Thread not found or could not be determined',
            ];
        }

        // Resolve sender (optional)
        $sender = $this->resolveSender($senderIdentifier);

        // Create post
        $post = $this->createPost($channel, $thread, $messageText, $messageType, $sender, $data);

        Log::info('[WebSocket Client] Post created', [
            'post_id' => $post->id,
            'post_ulid' => $post->ulid,
            'thread_uuid' => $thread->uuid,
        ]);

        return [
            'status' => 'success',
            'external_message_id' => $data['id'] ?? $data['message_id'] ?? null,
            'post_id' => $post->id,
            'post_ulid' => $post->ulid,
            'thread_uuid' => $thread->uuid,
            'channel_id' => $channel->id,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, error?: string}
     */
    protected function validateMessage(array $data): array
    {
        // For client connections, we're more lenient since external servers
        // may use different message formats
        if (empty($data)) {
            return ['valid' => false, 'error' => 'Empty message received'];
        }

        // Check if we have some content
        if (! isset($data['text']) && ! isset($data['message']) && ! isset($data['content'])) {
            return ['valid' => false, 'error' => 'Missing message content'];
        }

        return ['valid' => true];
    }

    /**
     * Resolve thread from identifier or channel's default thread
     */
    protected function resolveThread(Channel $channel, mixed $identifier): ?Thread
    {
        // Try to find thread by UUID if provided
        if (is_string($identifier) && trim($identifier) !== '') {
            $thread = Thread::query()
                ->where('uuid', $identifier)
                ->first();

            if ($thread) {
                return $thread;
            }
        }

        // Look for a thread associated with this channel
        // Check if channel has any related threads through channel relations
        $channelRelation = $channel->relations()
            ->where('relationable_type', Thread::class)
            ->first();

        if ($channelRelation) {
            return Thread::query()->find($channelRelation->relationable_id);
        }

        // If no specific thread found, try to find any active thread
        // This is a fallback - ideally channels should have explicit thread associations
        return Thread::query()
            ->where('status', 'open')
            ->latest()
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

            // Try by email or name if string looks like one
            if (str_contains($identifier, '@')) {
                $user = User::query()->where('email', $identifier)->first();
                if ($user) {
                    return $user;
                }
            }
        }

        // Try by ID
        if (is_int($identifier)) {
            return User::query()->find($identifier);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rawData
     */
    protected function createPost(
        Channel $channel,
        Thread $thread,
        string $text,
        string $type,
        ?User $sender,
        array $rawData
    ): Post {
        return DB::transaction(function () use ($channel, $thread, $text, $type, $sender, $rawData) {
            $post = $thread->posts()->create([
                'type' => $this->normalizePostType($type),
                'status' => Post::StatusActive,
                'text' => $text,
                'data' => [
                    'text' => $text,
                    'message_type' => $type,
                    'raw_message' => $rawData,
                ],
                'meta' => [
                    'source' => 'websocket_client_inbound',
                    'channel_id' => $channel->id,
                    'channel_type' => 'websocket',
                    'channel_mode' => 'client',
                    'raw_data' => $rawData,
                ],
            ]);

            if ($sender) {
                $post->attachRelation($sender, Post::RelationRoleSender);
            }

            // Associate post with channel
            $post->attachRelation($channel, 'channel');

            return $post;
        });
    }

    protected function normalizePostType(string $type): string
    {
        // Currently only TypeMessage is defined in Post model
        return Post::TypeMessage;
    }
}
