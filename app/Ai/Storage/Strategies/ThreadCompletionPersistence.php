<?php

namespace App\Ai\Storage\Strategies;

use App\Ai\Storage\Contracts\ThreadConversationPersistence;
use Illuminate\Support\Collection;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Storage\DatabaseConversationStore;

class ThreadCompletionPersistence implements ThreadConversationPersistence
{
    public function __construct(
        protected DatabaseConversationStore $store,
    ) {}

    public function latestConversationId(string|int $userId): ?string
    {
        return $this->store->latestConversationId($userId);
    }

    public function storeConversation(string|int|null $userId, string $title): string
    {
        return $this->store->storeConversation($userId, $title);
    }

    public function storeUserMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt): string
    {
        return $this->store->storeUserMessage($conversationId, $userId, $prompt);
    }

    public function storeAssistantMessage(
        string $conversationId,
        string|int|null $userId,
        AgentPrompt $prompt,
        AgentResponse $response
    ): string {
        return $this->store->storeAssistantMessage($conversationId, $userId, $prompt, $response);
    }

    /**
     * @return Collection<int, \Laravel\Ai\Messages\Message>
     */
    public function getLatestConversationMessages(string $conversationId, int $limit): Collection
    {
        return $this->store->getLatestConversationMessages($conversationId, $limit);
    }
}
