<?php

namespace App\Models\Server\Fulfillment\Concerns;

use App\Models\Server\Post;

trait HasPostMorphType
{
    public function getMorphClass(): string
    {
        return (new Post)->getMorphClass();
    }
}
