<?php

namespace App\Support\Channels;

use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRelation;
use App\Models\Server\ChannelRoute;

class ChannelApiResolver
{
    public function channel(string $identifier): Channel
    {
        return Channel::query()
            ->where(function ($query) use ($identifier): void {
                $query->where('uuid', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('channels.id', (int) $identifier);
                }
            })
            ->firstOrFail();
    }

    public function route(Channel $channel, string $identifier): ChannelRoute
    {
        return $channel->routes()
            ->where(function ($query) use ($identifier): void {
                $query->where('ulid', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('channel_routes.id', (int) $identifier);
                }
            })
            ->firstOrFail();
    }

    public function address(ChannelRoute $route, string $identifier): ChannelAddress
    {
        return $route->addresses()
            ->where(function ($query) use ($identifier): void {
                $query->where('ulid', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('channel_addresses.id', (int) $identifier);
                }
            })
            ->firstOrFail();
    }

    public function connection(Channel $channel, string $identifier): ChannelRelation
    {
        return $channel->connections()
            ->where(function ($query) use ($identifier): void {
                $query->where('ulid', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('channel_relations.id', (int) $identifier);
                }
            })
            ->firstOrFail();
    }
}
