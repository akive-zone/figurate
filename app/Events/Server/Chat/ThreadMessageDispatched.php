<?php

namespace App\Events\Server\Chat;

use App\Models\Server\Post;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadMessageDispatched
{
    use Dispatchable, SerializesModels;

    public function __construct(public Post $post) {}
}
