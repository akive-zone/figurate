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
use App\Ai\Middleware\Workflows\ApplyFulfillmentWorkflow;
use App\Ai\Middleware\Workflows\ComposeAndRouteResponse;
use App\Ai\Middleware\Workflows\ExecuteToolsAndActions;
use App\Ai\Middleware\Workflows\InitializeFulfillmentContext;
use App\Ai\Middleware\Workflows\PlanFulfillmentSteps;
use App\Ai\Middleware\Workflows\PostResponseLearning;
use App\Ai\Middleware\Workflows\ResolveAudienceContext;
use App\Ai\Middleware\Workflows\SelectPresenters;
use App\Ai\Middleware\Workflows\UseThreadConversationStore;
use App\Ai\Support\ChatToolResolver;
use App\Models\Server\Request;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class OrderAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersThreadConversations;

    public function __construct(
        public ?Thread $thread = null,
        public ?User $actor = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $request = $this->thread?->threadable;

        if (! $request instanceof Request) {
            $request = null;
        }
        $order = $request?->currentOrder();

        if (! $request) {
            return 'You are OrderAgent. Help askers navigate booked orders and fulfillment.';
        }

        $orderContext = $order
            ? "- Order #{$order->id} status: {$order->status}"
            : '- No order exists yet.';

        return "You are OrderAgent for the flow.\n".
            "Current fulfillment context:\n".
            "- Request #{$request->id} status: {$request->status}\n".
            "{$orderContext}\n\n".
            'Your role:\n'.
            '- Guide the asker through booked-order fulfillment.\n'.
            '- Highlight pending confirmations and milestones.\n'.
            '- Keep responses direct and operational.';
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
            new InitializeFulfillmentContext,
            new ResolveAudienceContext,
            new SelectPresenters,
            new ApplyFulfillmentWorkflow,
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
