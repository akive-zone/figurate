<?php

namespace App\Ai\Tools;

use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
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
        $channel = $this->resolveChannel();
        $serviceRequest = $this->resolveServiceRequest($channel);

        if ($channel && ! $this->channelAllowsActor($channel, $serviceRequest)) {
            return $this->encodeError('Actor does not have access to this channel flow.');
        }

        $order = $serviceRequest?->currentOrder();

        return json_encode([
            'ok' => true,
            'flow' => [
                'stage' => $this->inferStage($serviceRequest, $order),
                'channel' => $channel ? [
                    'id' => $channel->id,
                    'uuid' => $channel->uuid,
                    'status' => $channel->status,
                ] : null,
                'request' => $serviceRequest ? [
                    'id' => $serviceRequest->id,
                    'ulid' => $serviceRequest->ulid,
                    'type' => $serviceRequest->type,
                    'status' => $serviceRequest->status,
                    'flow_type' => $serviceRequest->flow_type,
                    'title' => $serviceRequest->title,
                    'description' => $serviceRequest->description,
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
                'recommended_next_actions' => $this->recommendedActions($serviceRequest, $order),
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

    protected function resolveChannel(): ?Channel
    {
        $threadable = $this->thread->threadable;

        if ($threadable instanceof Channel) {
            return $threadable;
        }

        if ($threadable instanceof ServiceRequest) {
            return $threadable->channels()->latest('channels.id')->first();
        }

        return null;
    }

    protected function resolveServiceRequest(?Channel $channel): ?ServiceRequest
    {
        $threadable = $this->thread->threadable;

        if ($threadable instanceof ServiceRequest) {
            return $threadable;
        }

        return $channel?->primaryRequest();
    }

    protected function channelAllowsActor(Channel $channel, ?ServiceRequest $serviceRequest): bool
    {
        if ($serviceRequest) {
            return $serviceRequest->hasParticipant($this->actor);
        }

        return $channel->hasActor($this->actor);
    }

    protected function inferStage(?ServiceRequest $serviceRequest, mixed $order): string
    {
        if (! $serviceRequest) {
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
    protected function recommendedActions(?ServiceRequest $serviceRequest, mixed $order): array
    {
        if (! $serviceRequest) {
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
