<?php

namespace App\Events\Server\Chat;

use App\Models\Server\Thread;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationThreadCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Thread $thread) {}
}
