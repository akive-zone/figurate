<?php

namespace App\Ai\Storage;

use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

class ThreadConversationStore implements ConversationStore
{
    public function __construct(
        protected ConversationPersistenceResolver $resolver,
        protected ?string $requestedMode = null,
    ) {}

    public function latestConversationId(string|int $userId): ?string
    {
        return $this->resolver->primary($this->requestedMode)->latestConversationId($userId);
    }

    public function storeConversation(string|int|null $userId, string $title): string
    {
        return $this->resolver->primary($this->requestedMode)->storeConversation($userId, $title);
    }

    public function storeUserMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt): string
    {
        $messageId = $this->resolver->primary($this->requestedMode)->storeUserMessage($conversationId, $userId, $prompt);

        $secondary = $this->resolver->secondary($this->requestedMode);

        if ($secondary !== null) {
            $secondary->storeUserMessage($conversationId, $userId, $prompt);
        }

        return $messageId;
    }

    public function storeAssistantMessage(
        string $conversationId,
        string|int|null $userId,
        AgentPrompt $prompt,
        AgentResponse $response
    ): string {
        $messageId = $this->resolver->primary($this->requestedMode)->storeAssistantMessage($conversationId, $userId, $prompt, $response);

        $secondary = $this->resolver->secondary($this->requestedMode);

        if ($secondary !== null) {
            $secondary->storeAssistantMessage($conversationId, $userId, $prompt, $response);
        }

        return $messageId;
    }

    /**
     * @return Collection<int, \Laravel\Ai\Messages\Message>
     */
    public function getLatestConversationMessages(string $conversationId, int $limit): Collection
    {
        return $this->resolver->primary($this->requestedMode)->getLatestConversationMessages($conversationId, $limit);
    }
}
