<?php

namespace App\Events\Server\Chat;

use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PreparingMessageResponse
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public Post $post,
        public Thread $thread,
        public Space $space,
        public User $actor,
        public array $body,
        public int $status,
        public bool $duplicate = false,
    ) {}
}
