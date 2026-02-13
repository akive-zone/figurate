<?php

namespace App\Ai\Tools;

use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class AcknowledgeAssessmentTool implements Tool
{
    public function __construct(
        protected Thread $thread,
        protected ServiceRequest $serviceRequest,
        protected User $actor,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Acknowledge the current order assessment as the asker.';
    }

    /**
     * Execute the tool.
     */
    public function handle(ToolRequest $request): Stringable|string
    {
        if (! $this->serviceRequest->hasUserActor($this->actor, ServiceRequest::ActionAsker)) {
            return $this->encodeError('Only the request asker can acknowledge assessment.');
        }

        $order = $this->serviceRequest->currentOrder();

        if (! $order) {
            return $this->encodeError('No order exists for this request.');
        }

        $assessment = $order->assessment();

        if (! $assessment) {
            return $this->encodeError('No assessment exists for this order.');
        }

        $assessment->forceFill([
            'type' => 'assessment.acknowledged',
            'status' => 'acknowledged',
            'payload' => array_merge($assessment->payload ?? [], [
                'acknowledged_at' => now()->toIso8601String(),
            ]),
            'meta' => array_merge($assessment->meta ?? [], [
                'source' => 'tool.acknowledge_assessment',
            ]),
            'occurred_at' => now(),
        ])->save();

        $order->forceFill([
            'status' => 'assessment_acknowledged',
        ])->save();

        $note = trim((string) ($request['note'] ?? ''));

        $this->thread->messages()->create([
            'senderable_type' => null,
            'senderable_id' => null,
            'type' => 'system',
            'tag' => 'assessment_acknowledged',
            'body' => $note !== ''
                ? "Assessment #{$assessment->id} acknowledged. {$note}"
                : "Assessment #{$assessment->id} acknowledged.",
            'attachments' => null,
            'meta' => [
                'source' => 'tool',
                'tool' => self::class,
                'order_id' => $order->id,
                'assessment_id' => $assessment->id,
            ],
        ]);

        return json_encode([
            'ok' => true,
            'assessment_id' => $assessment->id,
            'order_id' => $order->id,
            'status' => $assessment->status,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'note' => $schema->string(),
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
