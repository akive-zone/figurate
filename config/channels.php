<?php

use App\Models\Server\Channel;
use App\Support\Channels\Drivers\GenericChannelDriver;

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
        Channel::DriverMcp => GenericChannelDriver::class,
        'activitypub' => GenericChannelDriver::class,
        'nostr' => GenericChannelDriver::class,
    ],
];
