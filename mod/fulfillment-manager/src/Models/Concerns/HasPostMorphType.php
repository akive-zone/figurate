<?php

namespace Figurate\FulfillmentManager\Models\Concerns;

use App\Models\Server\Post;

trait HasPostMorphType
{
    public function getMorphClass(): string
    {
        return (new Post)->getMorphClass();
    }
}
