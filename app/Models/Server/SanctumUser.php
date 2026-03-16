<?php

namespace App\Models\Server;

use Laravel\Sanctum\HasApiTokens;

class SanctumUser extends User
{
    use HasApiTokens;
}
