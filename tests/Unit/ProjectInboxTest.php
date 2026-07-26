<?php

namespace Tests\Unit;

use App\Features\Actions\Chat\ProjectInbox;
use App\Models\Server\Inbox;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\ThreadEvent;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ProjectInboxTest extends TestCase
{
    public function test_it_projects_a_thread_summary_to_inbox(): void
    {
        $user = $this->makeUser(1);
        $thread = $this->makeThread(10);

        $projected = $this->makeAction()->project(
            $user,
            $thread,
            Inbox::KindThread,
            'Conversation summary',
            'Awaiting a response from the seller.',
            'thread_summary',
            ['phase' => 'follow_up'],
        );

        $this->assertInstanceOf(Inbox::class, $projected);
        $this->assertSame($thread->getKey(), $projected->thread_id);
        $this->assertSame($thread->getMorphClass(), $projected->inboxable_type);
        $this->assertSame($thread->getKey(), $projected->inboxable_id);
        $this->assertSame(Inbox::KindThread, $projected->kind);
    }

    public function test_it_projects_a_message_to_inbox_with_thread_context(): void
    {
        $user = $this->makeUser(1);
        $thread = $this->makeThread(10);
        $message = $this->makeMessage($thread, 99);

        $projected = $this->makeAction()->project(
            $user,
            $message,
            Inbox::KindThreadMessage,
            'New message',
            'Can you share an update?',
            'peer_message',
            ['message_type' => 'text'],
        );

        $this->assertInstanceOf(Inbox::class, $projected);
        $this->assertSame($thread->getKey(), $projected->thread_id);
        $this->assertSame($message->getMorphClass(), $projected->inboxable_type);
        $this->assertSame($message->getKey(), $projected->inboxable_id);
        $this->assertSame(Inbox::KindThreadMessage, $projected->kind);
    }

    public function test_it_projects_a_thread_event_to_inbox_with_thread_context(): void
    {
        $user = $this->makeUser(1);
        $thread = $this->makeThread(10);
        $threadEvent = $this->makeThreadEvent($thread, 55);

        $projected = $this->makeAction()->project(
            $user,
            $threadEvent,
            Inbox::KindThreadEvent,
            'Workflow updated',
            'Observer review completed.',
            'observer',
            ['state' => ThreadEvent::StateCompleted],
        );

        $this->assertInstanceOf(Inbox::class, $projected);
        $this->assertSame($thread->getKey(), $projected->thread_id);
        $this->assertSame($threadEvent->getMorphClass(), $projected->inboxable_type);
        $this->assertSame($threadEvent->getKey(), $projected->inboxable_id);
        $this->assertSame(Inbox::KindThreadEvent, $projected->kind);
    }

    protected function makeAction(): ProjectInbox
    {
        return new class extends ProjectInbox
        {
            protected function persistInbox(User $user, Model $inboxable, ?Thread $thread, array $attributes): Inbox
            {
                return new Inbox([
                    'user_id' => $user->getKey(),
                    'thread_id' => $thread?->getKey(),
                    'inboxable_type' => $inboxable->getMorphClass(),
                    'inboxable_id' => $inboxable->getKey(),
                    'kind' => $attributes['kind'],
                    'status' => $attributes['status'],
                    'title' => $attributes['title'],
                    'summary' => $attributes['summary'],
                    'source' => $attributes['source'],
                    'payload' => $attributes['payload'],
                ]);
            }
        };
    }

    protected function makeThread(int $id): Thread
    {
        $thread = new Thread([
            'purpose' => Thread::PurposeMain,
            'title' => 'Test Thread',
        ]);
        $thread->id = $id;
        $thread->exists = true;

        return $thread;
    }

    protected function makeMessage(Thread $thread, int $id): Post
    {
        $message = new Post([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'text' => 'Can you share an update?',
            'attachments' => null,
        ]);
        $message->id = $id;
        $message->exists = true;
        $message->postable_type = $thread->getMorphClass();
        $message->postable_id = $thread->getKey();
        $message->setRelation('postable', $thread);
        $message->setRelation('messageable', $thread);

        return $message;
    }

    protected function makeThreadEvent(Thread $thread, int $id): ThreadEvent
    {
        $threadEvent = new ThreadEvent([
            'thread_id' => $thread->getKey(),
            'state' => ThreadEvent::StateCompleted,
        ]);
        $threadEvent->id = $id;
        $threadEvent->exists = true;
        $threadEvent->setRelation('thread', $thread);

        return $threadEvent;
    }

    protected function makeUser(int $id): User
    {
        $user = new User([
            'name' => "User {$id}",
            'email' => "user{$id}@example.com",
        ]);
        $user->id = $id;
        $user->exists = true;

        return $user;
    }
}
