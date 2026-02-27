<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\RemembersThreadConversations;
use App\Ai\Middleware\Rules\ApplyPresenterResponseRules;
use App\Ai\Middleware\Rules\ApplySafetyAndPolicyRules;
use App\Ai\Middleware\Rules\EnforceActorPermissions;
use App\Ai\Middleware\Rules\EnforceThreadParticipation;
use App\Ai\Middleware\Rules\EnforceToolBudgetAndTimeouts;
use App\Ai\Middleware\Rules\PreventDuplicateProcessing;
use App\Ai\Middleware\Rules\RequireEvidenceForDecisions;
use App\Ai\Middleware\Rules\ResponseQualityGate;
use App\Ai\Middleware\Rules\ValidateInputContract;
use App\Ai\Middleware\Workflows\ComposeAndRouteResponse;
use App\Ai\Middleware\Workflows\ExecuteToolsAndActions;
use App\Ai\Middleware\Workflows\InitializeConversationContext;
use App\Ai\Middleware\Workflows\PlanFulfillmentSteps;
use App\Ai\Middleware\Workflows\PostResponseLearning;
use App\Ai\Middleware\Workflows\ResolveAudienceContext;
use App\Ai\Middleware\Workflows\SelectPresenters;
use App\Ai\Middleware\Workflows\UseThreadConversationStore;
use App\Ai\Support\ChatToolResolver;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class PresenterAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersThreadConversations;

    public function __construct(
        public ?Thread $thread = null,
        public ?User $actor = null,
        protected ?string $presenterActorKey = null,
    ) {}

    public function setPresenterActorKey(?string $actorKey): static
    {
        $this->presenterActorKey = is_string($actorKey) && $actorKey !== ''
            ? $actorKey
            : null;

        return $this;
    }

    public function presenterActorKey(): ?string
    {
        return $this->presenterActorKey;
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return "You are an Agent for conversation orchestration.\n"
            ."Operating mode:\n"
            ."- Use tools first to inspect flow/state before committing decisions.\n"
            ."- When process guidance is unclear, call skills tools to find relevant local skills.\n"
            ."- Use knowledge retrieval (RAG / file search) whenever facts are document-backed.\n"
            ."- Keep responses concise, operational, and evidence-aware.\n"
            .'- When state-changing actions are needed, call the appropriate tool instead of free-text promises.';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        if (! $this->thread || ! $this->actor) {
            return [];
        }

        return app(ChatToolResolver::class)->resolve($this->thread, $this->actor);
    }

    public function middleware(): array
    {
        return [
            new UseThreadConversationStore,
            new InitializeConversationContext,
            new ResolveAudienceContext,
            new SelectPresenters,
            new PlanFulfillmentSteps,
            new ExecuteToolsAndActions,
            new ComposeAndRouteResponse,
            new PostResponseLearning,
            new EnforceActorPermissions,
            new EnforceThreadParticipation,
            new PreventDuplicateProcessing,
            new ValidateInputContract,
            new ApplySafetyAndPolicyRules,
            new EnforceToolBudgetAndTimeouts,
            new RequireEvidenceForDecisions,
            new ResponseQualityGate,
            new ApplyPresenterResponseRules,
        ];
    }
}
