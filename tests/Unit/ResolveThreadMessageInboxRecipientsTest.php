<?php

namespace Tests\Unit;

use App\Actions\Server\Chat\ResolveThreadMessageInboxRecipients;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ResolveThreadMessageInboxRecipientsTest extends TestCase
{
    public function test_it_returns_active_human_participants_except_the_sender(): void
    {
        $sender = $this->makeUser(1);
        $recipient = $this->makeUser(2);
        $paused = $this->makeUser(3);
        $thread = $this->makeThread([
            $this->makeActor($sender),
            $this->makeActor($recipient),
            $this->makeActor($paused, ThreadActor::StatusPaused),
        ]);
        $message = $this->makeMessage($thread, $sender, 'peer_message');

        $recipients = (new ResolveThreadMessageInboxRecipients)($message);

        $this->assertCount(1, $recipients);
        $this->assertSame($recipient->getKey(), $recipients->first()?->getKey());
    }

    public function test_it_returns_all_active_human_participants_for_agent_messages(): void
    {
        $firstRecipient = $this->makeUser(1);
        $secondRecipient = $this->makeUser(2);
        $thread = $this->makeThread([
            $this->makeActor($firstRecipient),
            $this->makeActor($secondRecipient),
        ]);
        $message = $this->makeMessage($thread, null, 'agent_response');

        $recipients = (new ResolveThreadMessageInboxRecipients)($message);

        $this->assertCount(2, $recipients);
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

    protected function makeActor(User $user, string $status = ThreadActor::StatusActive): ThreadActor
    {
        $actor = new ThreadActor([
            'actorable_type' => User::class,
            'actorable_id' => $user->getKey(),
            'status' => $status,
            'role' => ThreadActor::RoleMember,
        ]);
        $actor->setRelation('actorable', $user);

        return $actor;
    }

    /**
     * @param  list<ThreadActor>  $actors
     */
    protected function makeThread(array $actors): Thread
    {
        $thread = new Thread([
            'purpose' => Thread::PurposeMain,
            'title' => 'Test Thread',
        ]);
        $thread->id = 10;
        $thread->exists = true;
        $thread->setRelation('actors', new Collection($actors));

        return $thread;
    }

    protected function makeMessage(Thread $thread, ?User $sender, string $source): Message
    {
        $message = new Message([
            'type' => 'text',
            'text' => 'Test message',
            'attachments' => null,
            'meta' => ['source' => $source],
            'senderable_type' => $sender?->getMorphClass(),
            'senderable_id' => $sender?->getKey(),
        ]);
        $message->id = 99;
        $message->exists = true;
        $message->setRelation('messageable', $thread);

        return $message;
    }
}
