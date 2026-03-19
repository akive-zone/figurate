<?php

namespace App\Events\Accounts;

use App\Models\Server\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnsurePrimaryAccountForUserRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}
}
