<?php

namespace App\Models\Server;

use Laravel\Passport\HasApiTokens;

class PassportUser extends BaseUser
{
    use HasApiTokens;
}
