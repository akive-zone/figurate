<?php

namespace Figurate\Auth\Support;

use App\Models\Server\User;

final class RobotUsers
{
    public const Robot = 'robot';

    public static function isRobot(User $user): bool
    {
        return $user->type === self::Robot;
    }
}
