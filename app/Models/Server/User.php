<?php

namespace App\Models\Server;

use Laravel\Sanctum\HasApiTokens;

class User extends BaseUser
{
    use HasApiTokens;
}
