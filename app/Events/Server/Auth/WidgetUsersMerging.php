<?php

namespace App\Events\Server\Auth;

use App\Models\Server\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WidgetUsersMerging
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $source,
        public User $target,
    ) {}
}
