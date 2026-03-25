<?php

namespace Tests\Unit;

use App\Ai\Storage\ConversationPersistenceResolver;
use App\Models\Server\Message;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Notifications\Channels\CompletionChannel;
use App\Notifications\Channels\ContinuationChannel;
use App\Notifications\Channels\CoordinationChannel;
use App\Notifications\Server\Chat\ThreadMessageNotification;
use PHPUnit\Framework\TestCase;

class ThreadMessageNotificationTest extends TestCase
{
    public function test_it_routes_notifications_through_completion_continuation_and_coordination_transports(): void
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

        $message = new Message([
            'type' => 'text',
            'text' => 'The artisan has arrived.',
            'attachments' => null,
            'meta' => ['source' => 'peer_message'],
            'senderable_type' => User::class,
            'senderable_id' => 7,
        ]);
        $message->id = 99;
        $message->ulid = '01JQ3X2CZJ4EXAMPLE000001';
        $message->exists = true;
        $message->setRelation('messageable', $thread);

        $subject = new User([
            'type' => User::TypeSubject,
        ]);
        $robot = new User([
            'type' => User::TypeRobot,
        ]);
        $robot->id = 7;
        $robot->uuid = 'robot-uuid';

        $notification = new ThreadMessageNotification($message);

        $this->assertSame([CoordinationChannel::class], $notification->via($subject));
        $this->assertSame([CoordinationChannel::class], $notification->via($robot));

        $completionMessage = new Message([
            'type' => 'text',
            'text' => 'The artisan has arrived.',
            'attachments' => null,
            'meta' => [
                'source' => 'peer_message',
                'conversation_persistence' => ConversationPersistenceResolver::ThreadCompletion,
            ],
            'senderable_type' => User::class,
            'senderable_id' => 7,
        ]);
        $completionMessage->id = 100;
        $completionMessage->ulid = '01JQ3X2CZJ4EXAMPLE000002';
        $completionMessage->exists = true;
        $completionMessage->setRelation('messageable', $thread);

        $completionNotification = new ThreadMessageNotification($completionMessage);

        $this->assertSame([CompletionChannel::class], $completionNotification->via($robot));

        $continuationMessage = new Message([
            'type' => 'text',
            'text' => 'The artisan has arrived.',
            'attachments' => null,
            'meta' => [
                'source' => 'peer_message',
                'conversation_persistence' => ConversationPersistenceResolver::ThreadContinuation,
            ],
            'senderable_type' => User::class,
            'senderable_id' => 7,
        ]);
        $continuationMessage->id = 101;
        $continuationMessage->ulid = '01JQ3X2CZJ4EXAMPLE000003';
        $continuationMessage->exists = true;
        $continuationMessage->setRelation('messageable', $thread);

        $continuationNotification = new ThreadMessageNotification($continuationMessage);

        $this->assertSame([ContinuationChannel::class], $continuationNotification->via($robot));

        $payload = $notification->toArray($subject);

        $this->assertSame('thread_message', $payload['kind']);
        $this->assertSame('thread-uuid', data_get($payload, 'thread.id'));
        $this->assertSame('space-uuid', data_get($payload, 'space.id'));
        $this->assertSame(99, data_get($payload, 'message.id'));
        $this->assertSame('peer_message', data_get($payload, 'message.source'));
        $this->assertNull(data_get($payload, 'message.conversation_persistence'));
        $this->assertNull(data_get($payload, 'inbox.id'));
        $this->assertSame('New message', data_get($payload, 'inbox.title'));
        $this->assertSame('The artisan has arrived.', data_get($payload, 'inbox.summary'));

        $coordination = $notification->toCoordination($robot);

        $this->assertIsArray($coordination);
        $this->assertSame($thread, $coordination['thread']);
        $this->assertSame($space, $coordination['space']);
        $this->assertSame($message, $coordination['message']);
        $this->assertSame($robot, $coordination['recipient']);

        $promptTransport = $completionNotification->toPromptTransport($robot);

        $this->assertIsArray($promptTransport);
        $this->assertSame($thread, $promptTransport['thread']);
        $this->assertSame($completionMessage, $promptTransport['message']);
        $this->assertSame(ConversationPersistenceResolver::ThreadCompletion, $promptTransport['conversation_persistence']);
    }
}
