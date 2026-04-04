<?php

use App\Models\Server\Channel;
use App\Support\Channels\Drivers\GenericChannelDriver;
use App\Support\Channels\Drivers\McpChannelDriver;
use App\Support\Channels\Drivers\NostrChannelDriver;
use App\Support\Channels\Drivers\StdioChannelDriver;
use App\Support\Channels\Drivers\WebhookChannelDriver;
use App\Support\Channels\Drivers\WebsocketChannelDriver;

return [
    /*
    |--------------------------------------------------------------------------
    | Channel Drivers
    |--------------------------------------------------------------------------
    |
    | Channels define how traffic exits and enters this system, including
    | message delivery and MCP context transport endpoints.
    |
    */
    'drivers' => [
        Channel::DriverGeneric => GenericChannelDriver::class,
        Channel::DriverMcp => McpChannelDriver::class,
        Channel::DriverStdio => StdioChannelDriver::class,
        'webhook' => WebhookChannelDriver::class,
        'websocket' => WebsocketChannelDriver::class,
        'nostr' => NostrChannelDriver::class,
        'activitypub' => WebhookChannelDriver::class,
    ],
];
