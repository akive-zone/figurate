<?php

use App\Models\Server\Fulfillment\Request;
use App\Models\Server\Post;

return [
    'post_models' => [
        'request' => env('AI_REQUEST_POST_MODEL', Request::class),
        'default' => Post::class,
    ],
];
