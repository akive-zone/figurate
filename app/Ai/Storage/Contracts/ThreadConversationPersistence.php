<?php

namespace App\Ai\Storage\Contracts;

use Illuminate\Support\Collection;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

interface ThreadConversationPersistence
{
    public function latestConversationId(string|int $userId): ?string;

    public function storeConversation(string|int|null $userId, string $title): string;

    public function storeUserMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt): string;

    public function storeAssistantMessage(
        string $conversationId,
        string|int|null $userId,
        AgentPrompt $prompt,
        AgentResponse $response
    ): string;

    /**
     * @return Collection<int, \Laravel\Ai\Messages\Message>
     */
    public function getLatestConversationMessages(string $conversationId, int $limit): Collection;
}
