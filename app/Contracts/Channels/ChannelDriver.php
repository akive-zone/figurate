<?php

namespace App\Contracts\Channels;

use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Thread;

interface ChannelDriver
{
    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    public function send(Channel $channel, Thread $thread, Message $message, array $bindingConfig = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeInbound(Channel $channel, array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeReceipt(Channel $channel, array $payload): array;
}
