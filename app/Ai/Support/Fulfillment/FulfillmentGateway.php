<?php

namespace App\Ai\Support\Fulfillment;

use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;

interface FulfillmentGateway
{
    public function currentOrder(Post $requestPost): mixed;

    public function quoteForRequest(Post $requestPost, int $quoteId): mixed;

    public function createOrderFromQuote(Thread $thread, Post $requestPost, User $actor, int $quoteId, string $status): array;

    public function acknowledgeAssessment(Thread $thread, Post $requestPost, User $actor, ?string $note = null): array;

    public function upsertAssessment(Thread $thread, Post $requestPost, User $actor, string $notes, string $status): array;
}
