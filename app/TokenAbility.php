<?php

namespace App;

enum TokenAbility: string
{
    case Compose = 'compose';

    case McpUse = 'mcp:use';

    case AcpUse = 'acp:use';

    case A2aMessageSend = 'a2a:message.send';

    case A2aTaskRead = 'a2a:task.read';

    case A2aTaskCancel = 'a2a:task.cancel';

    case A2aPushConfigManage = 'a2a:push.config.manage';

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
    public static function defaultRobotAbilities(): array
    {
        return [
            self::McpUse->value,
            self::AcpUse->value,
            self::A2aMessageSend->value,
            self::A2aTaskRead->value,
            self::A2aTaskCancel->value,
            self::A2aPushConfigManage->value,
        ];
    }
}
