<?php

namespace App;

enum TokenAbility: string
{
    case Compose = 'compose';

    case NodesRead = 'nodes:read';

    case NodesWrite = 'nodes:write';

    case EdgesRead = 'edges:read';

    case EdgesWrite = 'edges:write';

    case FormsSubmit = 'forms:submit';

    case ChannelsManage = 'channels:manage';

    case CredentialsManage = 'credentials:manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $ability): string => $ability->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function thirdPartyValues(): array
    {
        return collect(array_keys(config('token-abilities', [])))
            ->reject(fn (string $ability): bool => $ability === self::Compose->value)
            ->values()
            ->all();
    }
}
