<?php

use App\Models\Server\Channel;
use App\Support\Channels\Drivers\A2aChannelDriver;
use App\Support\Channels\Drivers\AcpChannelDriver;
use App\Support\Channels\Drivers\GenericChannelDriver;
use App\Support\Channels\Drivers\McpChannelDriver;
use App\Support\Channels\Drivers\NostrChannelDriver;
use App\Support\Channels\Drivers\StdioChannelDriver;
use App\Support\Channels\Drivers\WebhookChannelDriver;
use App\Support\Channels\Drivers\WebsocketChannelDriver;

return [
    /*
    |--------------------------------------------------------------------------
    | Channel Systems
    |--------------------------------------------------------------------------
    |
    | Channels define the carrier or system that traffic uses to enter and
    | leave this application. Protocol semantics such as MCP, A2A, and ACP
    | are configured per connection.
    |
    */
    'systems' => [
        Channel::DriverGeneric => GenericChannelDriver::class,
        Channel::DriverMcp => McpChannelDriver::class,
        Channel::DriverA2a => A2aChannelDriver::class,
        Channel::DriverAcp => AcpChannelDriver::class,
        Channel::DriverStdio => StdioChannelDriver::class,
        'webhook' => WebhookChannelDriver::class,
        'websocket' => WebsocketChannelDriver::class,
        'nostr' => NostrChannelDriver::class,
        'activitypub' => WebhookChannelDriver::class,
    ],

    'drivers' => [
        Channel::DriverGeneric => GenericChannelDriver::class,
        Channel::DriverMcp => McpChannelDriver::class,
        Channel::DriverA2a => A2aChannelDriver::class,
        Channel::DriverAcp => AcpChannelDriver::class,
        Channel::DriverStdio => StdioChannelDriver::class,
        'webhook' => WebhookChannelDriver::class,
        'websocket' => WebsocketChannelDriver::class,
        'nostr' => NostrChannelDriver::class,
        'activitypub' => WebhookChannelDriver::class,
    ],
];
