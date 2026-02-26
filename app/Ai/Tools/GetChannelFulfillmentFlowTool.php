<?php

namespace App\Ai\Tools;

use App\Ai\Support\FulfillmentContext;
use App\Ai\Support\ThreadContextResolver;
use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class GetChannelFulfillmentFlowTool implements Tool
{
    public function __construct(
        protected Thread $thread,
        protected User $actor,
        protected FulfillmentContext $fulfillmentContext = new FulfillmentContext,
        protected ThreadContextResolver $threadContextResolver = new ThreadContextResolver,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the current channel fulfillment flow and recommended next actions. Use this before asking follow-up questions.';
    }

    /**
     * Execute the tool.
     */
    public function handle(ToolRequest $request): Stringable|string
    {
        $channel = $this->threadContextResolver->resolveChannel($this->thread);
        $requestPost = $this->fulfillmentContext->resolveSubjectFromThread($this->thread);

        if ($channel && ! $this->channelAllowsActor($channel, $requestPost)) {
            return $this->encodeError('Actor does not have access to this channel flow.');
        }

        $order = $requestPost ? $this->fulfillmentContext->currentOrder($requestPost) : null;

        return json_encode([
            'ok' => true,
            'flow' => [
                'stage' => $this->inferStage($requestPost, $order),
                'channel' => $channel ? [
                    'id' => $channel->id,
                    'uuid' => $channel->uuid,
                    'status' => $channel->status,
                ] : null,
                'request' => $requestPost ? [
                    'id' => $requestPost->id,
                    'ulid' => $requestPost->ulid,
                    'type' => $requestPost->type,
                    'status' => $requestPost->status,
                    'flow_type' => $this->fulfillmentContext->flowType($requestPost),
                    'title' => $this->fulfillmentContext->title($requestPost),
                    'description' => $this->fulfillmentContext->description($requestPost),
                ] : null,
                'thread' => [
                    'id' => $this->thread->id,
                    'uuid' => $this->thread->uuid,
                    'purpose' => $this->thread->purpose,
                    'phase' => $this->thread->phase,
                    'status' => $this->thread->status,
                ],
                'order' => $order ? [
                    'id' => $order->id,
                    'status' => $order->status,
                ] : null,
                'recommended_next_actions' => $this->recommendedActions($requestPost, $order),
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function channelAllowsActor(Channel $channel, ?Post $requestPost): bool
    {
        if ($requestPost) {
            return $this->fulfillmentContext->hasParticipant($requestPost, $this->actor);
        }

        return $channel->hasActor($this->actor);
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

    /**
     * @return list<string>
     */
    protected function recommendedActions(?Post $requestPost, mixed $order): array
    {
        if (! $requestPost) {
            return [
                'Collect minimum scope details from user.',
                'Create request from conversation.',
                'Suggest matching profiles for the request.',
            ];
        }

        if (! $order) {
            return [
                'Confirm final request scope with user.',
                'Suggest matching profiles for quoting.',
                'Collect or review incoming quotes.',
            ];
        }

        return [
            'Track order fulfillment updates.',
            'Capture assessment/progress milestones.',
            'Confirm completion or follow-up actions.',
        ];
    }

    protected function encodeError(string $message): string
    {
        return json_encode([
            'ok' => false,
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES);
    }
}
