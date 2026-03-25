<?php

namespace Tests\Unit;

use App\Features\Actions\Conversation\ProjectThreadMessageToInbox;
use App\Models\Server\Inbox;
use App\Models\Server\Post;
use App\Models\Server\PostRelation;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ProjectThreadMessageToInboxTest extends TestCase
{
    public function test_it_projects_thread_messages_to_active_human_recipients_except_the_sender(): void
    {
        [$sender, $recipient] = $this->makeUsers();
        $thread = $this->makeThread();

        $message = $this->makeMessage(
            thread: $thread,
            sender: $sender,
            text: 'Need a reply on this request.',
            source: 'peer_message',
        );

        $action = new class extends ProjectThreadMessageToInbox
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
                ]);
            }
        };

        $projected = $action->execute($recipient, $message);

        $this->assertInstanceOf(Inbox::class, $projected);
        $this->assertSame($recipient->getKey(), $projected->user_id);
        $this->assertSame($thread->getKey(), $projected->thread_id);
        $this->assertSame($message->getMorphClass(), $projected->inboxable_type);
        $this->assertSame($message->getKey(), $projected->inboxable_id);
        $this->assertSame('New message', $projected->title);
        $this->assertSame('Need a reply on this request.', $projected->summary);
    }

    public function test_it_projects_agent_updates_for_a_recipient(): void
    {
        [, $recipient] = $this->makeUsers();
        $thread = $this->makeThread();
        $message = $this->makeMessage(
            thread: $thread,
            sender: null,
            text: 'The agent completed the review.',
            source: 'agent_response',
        );

        $action = new class extends ProjectThreadMessageToInbox
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
                ]);
            }
        };

        $projected = $action->execute($recipient, $message);

        $this->assertInstanceOf(Inbox::class, $projected);
        $this->assertSame($thread->getKey(), $projected->thread_id);
        $this->assertSame($message->getMorphClass(), $projected->inboxable_type);
        $this->assertSame($message->getKey(), $projected->inboxable_id);
        $this->assertSame('Agent update', $projected->title);
        $this->assertSame('The agent completed the review.', $projected->summary);
    }

    protected function makeThread(): Thread
    {
        $thread = new Thread([
            'purpose' => Thread::PurposeMain,
            'title' => 'Test Thread',
        ]);
        $thread->id = 10;
        $thread->exists = true;

        return $thread;
    }

    protected function makeMessage(Thread $thread, ?User $sender, string $text, string $source): Post
    {
        $message = new Post([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => $text,
                'message_type' => 'text',
            ],
            'meta' => ['source' => $source],
        ]);
        $message->id = 99;
        $message->ulid = '01JNYM8Q1B6EXAMPLE0000001';
        $message->exists = true;
        $message->setRelation('postable', $thread);

        if ($sender) {
            $message->setRelation('senderRelation', new PostRelation([
                'relationable_type' => $sender->getMorphClass(),
                'relationable_id' => $sender->getKey(),
                'role' => Post::RelationRoleSender,
            ]));
        }

        return $message;
    }

    /**
     * @return array{0: User, 1: User}
     */
    protected function makeUsers(): array
    {
        return [$this->makeUser(1), $this->makeUser(2)];
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
