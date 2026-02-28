<?php

namespace App\Models\Server;

use Laravel\Sanctum\HasApiTokens;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

class User extends BaseUser implements HasPasskeys
{
    use HasApiTokens, InteractsWithPasskeys;
}
