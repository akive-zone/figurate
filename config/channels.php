<?php

use App\Models\Server\Channel;
use App\Support\Channels\Drivers\A2aChannelDriver;
use App\Support\Channels\Drivers\AcpChannelDriver;
use App\Support\Channels\Drivers\GenericChannelDriver;
use App\Support\Channels\Drivers\McpChannelDriver;
use App\Support\Channels\Drivers\NostrChannelDriver;

return [
    /*
    |--------------------------------------------------------------------------
    | Channel Protocols
    |--------------------------------------------------------------------------
    |
    | Protocol-based channel drivers. Each driver handles a specific
    | application-layer protocol and can work with multiple transports.
    |
    | Architecture:
    |   Protocol (driver): Defines message format and semantics
    |   Transport: Defines how messages are physically transmitted
    |
    | Examples:
    |   MCP over WebSocket:  driver='mcp', transport='websocket'
    |   MCP over STDIO:      driver='mcp', transport='stdio'
    |   Generic over HTTP:   driver='generic', transport='http'
    |   A2A over WebSocket:  driver='a2a', transport='websocket'
    |
    | Available Transports:
    |   - websocket (real-time bidirectional)
    |   - http (request/response)
    |   - webhook (HTTP callbacks)
    |   - stdio (standard input/output)
    |   - webrtc (peer-to-peer)
    |   - relay (Nostr relays)
    |
    */
    'protocols' => [
        // Core Protocol Drivers
        Channel::ProtocolGeneric => GenericChannelDriver::class,
        Channel::ProtocolMcp => McpChannelDriver::class,
        Channel::ProtocolA2a => A2aChannelDriver::class,
        Channel::ProtocolAcp => AcpChannelDriver::class,

        // Special-purpose Protocol Drivers
        'nostr' => NostrChannelDriver::class,  // Nostr protocol (uses relay or websocket transport)
    ],
];
