<?php

use App\Models\Server\Channel;
use App\Support\Channels\Drivers\GenericChannelDriver;
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
    |   Generic over HTTP:   driver='generic', transport='http'
    |   Nostr over relay:    driver='nostr', transport='relay'
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
        // Special-purpose Protocol Drivers
        Channel::ProtocolNostr => NostrChannelDriver::class,  // Nostr protocol (uses relay or websocket transport)
    ],

    'transports' => [
        Channel::TransportHttp,
        Channel::TransportWebhook,
        Channel::TransportWebsocket,
        Channel::TransportWebrtc,
        Channel::TransportRelay,
        Channel::TransportStdio,
    ],

];
