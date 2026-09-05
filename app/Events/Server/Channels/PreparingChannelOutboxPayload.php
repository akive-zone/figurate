<?php

namespace App\Events\Server\Channels;

use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRoute;
use App\Models\Server\Post;
use Illuminate\Foundation\Events\Dispatchable;

class PreparingChannelOutboxPayload
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Post $post,
        public Channel $channel,
        public ChannelRoute $route,
        public ChannelAddress $address,
        public array $payload,
    ) {}
}
