<?php

namespace Tests\Unit;

use App\Models\Server\Post;
use App\Models\Server\PostRelation;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Notifications\Channels\CoordinationChannel;
use App\Notifications\Server\Chat\ThreadMessageNotification;
use PHPUnit\Framework\TestCase;

class ThreadMessageNotificationTest extends TestCase
{
    public function test_it_routes_thread_messages_through_the_coordination_channel(): void
    {
        $space = new Space([
            'uuid' => 'space-uuid',
            'status' => 'open',
        ]);
        $space->id = 5;
        $space->exists = true;

        $thread = new Thread([
            'uuid' => 'thread-uuid',
            'title' => 'Coordination Thread',
            'purpose' => Thread::PurposeMain,
            'phase' => 'coordination',
            'status' => 'open',
        ]);
        $thread->id = 10;
        $thread->exists = true;
        $thread->setRelation('threadable', $space);

        $message = new Post([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => 'The artisan has arrived.',
                'message_type' => 'text',
            ],
            'meta' => ['source' => 'peer_message'],
        ]);
        $message->id = 99;
        $message->ulid = '01JQ3X2CZJ4EXAMPLE000001';
        $message->exists = true;
        $message->setRelation('postable', $thread);
        $message->setRelation('senderRelation', new PostRelation([
            'relationable_type' => User::class,
            'relationable_id' => 7,
            'role' => Post::RelationRoleSender,
        ]));

        $subject = new User([
            'type' => User::TypeSubject,
        ]);

        $notification = new ThreadMessageNotification($message);

        $this->assertSame([CoordinationChannel::class], $notification->via($subject));

        $payload = $notification->toArray($subject);

        $this->assertSame('thread_message', $payload['kind']);
        $this->assertSame('thread-uuid', data_get($payload, 'thread.id'));
        $this->assertSame('space-uuid', data_get($payload, 'space.id'));
        $this->assertSame(99, data_get($payload, 'message.id'));
        $this->assertSame('peer_message', data_get($payload, 'message.source'));
        $this->assertNull(data_get($payload, 'inbox.id'));
        $this->assertSame('New message', data_get($payload, 'inbox.title'));
        $this->assertSame('The artisan has arrived.', data_get($payload, 'inbox.summary'));
    }
}
