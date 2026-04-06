<?php

use App\Models\Server\Channel;
use App\Support\Channels\Drivers\A2aChannelDriver;
use App\Support\Channels\Drivers\AcpChannelDriver;
use App\Support\Channels\Drivers\GenericChannelDriver;
use App\Support\Channels\Drivers\McpChannelDriver;

return [
    /*
    |--------------------------------------------------------------------------
    | Channel Protocols
    |--------------------------------------------------------------------------
    |
    | Channels are now protocol-first. Transport details such as HTTP,
    | WebSocket, and stdio are configured per connection.
    |
    */
    'protocols' => [
        Channel::ProtocolGeneric => GenericChannelDriver::class,
        Channel::ProtocolMcp => McpChannelDriver::class,
        Channel::ProtocolA2a => A2aChannelDriver::class,
        Channel::ProtocolAcp => AcpChannelDriver::class,
    ],
];
