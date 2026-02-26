<?php

namespace App\Ai\Middleware\Workflows;

use App\Ai\Support\FulfillmentContext;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class ApplyFulfillmentWorkflow
{
    public function __construct(
        protected FulfillmentContext $fulfillmentContext = new FulfillmentContext,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $thread = $this->resolveThread($prompt);

        if (! $thread) {
            return $next($prompt);
        }

        $requestPost = $this->fulfillmentContext->resolveSubjectFromThread($thread);

        $order = $requestPost ? $this->fulfillmentContext->currentOrder($requestPost) : null;
        $stage = $this->inferStage($requestPost, $order);
        $workflowContext = $this->buildWorkflowContext($thread, $requestPost, $order, $stage);

        return $next($prompt->prepend($workflowContext));
    }

    protected function resolveThread(AgentPrompt $prompt): ?Thread
    {
        $agent = $prompt->agent;

        if (! property_exists($agent, 'thread')) {
            return null;
        }

        $thread = $agent->thread;

        return $thread instanceof Thread ? $thread : null;
    }

    protected function inferStage(?Post $requestPost, mixed $order): string
    {
        if (! $requestPost) {
            return 'intake';
        }

        if (! $order) {
            return 'request_open';
        }

        return 'order_active';
    }

    protected function buildWorkflowContext(Thread $thread, ?Post $requestPost, mixed $order, string $stage): string
    {
        $requestLabel = $requestPost ? "#{$requestPost->id} ({$requestPost->status})" : 'none';
        $orderLabel = $order ? "#{$order->id} ({$order->status})" : 'none';

        $stageRules = match ($stage) {
            'intake' => [
                '- Ask only for minimum scope details needed to create a request.',
                '- Move to request creation quickly once intent is clear.',
                '- Do not discuss booked-order execution yet.',
            ],
            'request_open' => [
                '- Confirm scope and constraints needed for quoting.',
                '- Propose next fulfillment step from the current request state.',
                '- Do not claim order execution updates when no order exists.',
            ],
            default => [
                '- Focus on execution, milestones, blockers, and confirmations.',
                '- Keep guidance operational and status-aware.',
                '- Do not switch back to intake questioning unless user requests it.',
            ],
        };

        return implode("\n", [
            'Fulfillment workflow context (system policy):',
            "- Stage: {$stage}",
            "- Thread: #{$thread->id} ({$thread->purpose}/{$thread->phase})",
            "- Request: {$requestLabel}",
            "- Order: {$orderLabel}",
            '- Before follow-up questions, consult your flow tool when state is unclear.',
            ...$stageRules,
        ]);
    }
}
