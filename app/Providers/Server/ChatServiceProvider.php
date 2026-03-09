<?php

namespace App\Providers\Server;

use App\Events\Server\Chat\ThreadMessageStored;
use App\Listeners\Server\Ai\RecordObserverAgentPrompted;
use App\Listeners\Server\Ai\RecordObserverAgentPrompting;
use App\Listeners\Server\Chat\QueueThreadObserversForPeerMessage;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Policies\Server\ChannelPolicy;
use App\Policies\Server\MessagePolicy;
use App\Policies\Server\ThreadPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\PromptingAgent;

class ChatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Thread::class, ThreadPolicy::class);

        Event::listen(ThreadMessageStored::class, QueueThreadObserversForPeerMessage::class);
        Event::listen(PromptingAgent::class, RecordObserverAgentPrompting::class);
        Event::listen(AgentPrompted::class, RecordObserverAgentPrompted::class);
    }
}
