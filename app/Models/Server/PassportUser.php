<?php

namespace App\Models\Server;

use Laravel\Passport\HasApiTokens;

class PassportUser extends User
{
    use HasApiTokens;
}
