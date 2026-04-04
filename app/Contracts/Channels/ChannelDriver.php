<?php

namespace App\Contracts\Channels;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;

interface ChannelDriver
{
    public function key(): string;

    /**
     * @return list<string>
     */
    public function supportedTransports(): array;

    /**
     * @return list<string>
     */
    public function supportedProtocols(): array;

    /**
     * @return list<string>
     */
    public function capabilities(?Channel $channel = null): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function prepareForRegistration(array $attributes): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function prepareForUpdate(Channel $channel, array $attributes): array;

    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    public function send(Channel $channel, Thread $thread, Post $message, array $bindingConfig = []): array;

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
