<?php

namespace App\Events\Server\Chat;

use App\Features\Actions\Chat\ThreadMessageEntry;
use Illuminate\Foundation\Events\Dispatchable;

class PreparingThreadMessage
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public ThreadMessageEntry $entry,
        public array $meta,
    ) {}
}
