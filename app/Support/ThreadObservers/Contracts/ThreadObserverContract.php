<?php

namespace App\Support\ThreadObservers\Contracts;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Support\ThreadObservers\ObserverResult;

interface ThreadObserverContract
{
    public function key(): string;

    public function observe(Thread $thread, Message $message): ?ObserverResult;
}
