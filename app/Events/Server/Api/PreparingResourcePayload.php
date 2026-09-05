<?php

namespace App\Events\Server\Api;

use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PreparingResourcePayload
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Model $resource,
        public User $actor,
        public array $payload,
    ) {}
}
