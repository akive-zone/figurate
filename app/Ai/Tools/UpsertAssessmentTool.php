<?php

namespace App\Ai\Tools;

use App\Models\Server\Assessment;
use App\Models\Server\Order;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class UpsertAssessmentTool implements Tool
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
        return 'Create or update the order assessment for the current request. Use this when a worker posts assessment results.';
    }

    /**
     * Execute the tool.
     */
    public function handle(ToolRequest $request): Stringable|string
    {
        $order = $this->serviceRequest->currentOrder();

        if (! $order) {
            return $this->encodeError('No order exists for this request.');
        }

        if ($order->sellerProfile?->user_id !== $this->actor->id) {
            return $this->encodeError('Only the assigned worker can upsert assessments.');
        }

        $status = trim((string) ($request['status'] ?? 'submitted'));
        $allowedStatuses = ['submitted', 'revision_requested', 'acknowledged'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'submitted';
        }

        $notes = trim((string) ($request['notes'] ?? ''));
        $assessment = $order->assessment() ?? new Assessment;

        $assessment->fill([
            'type' => 'assessment.upserted',
            'status' => $status,
            'payload' => [
                'notes' => $notes !== '' ? $notes : null,
                'acknowledged_at' => $status === 'acknowledged' ? now()?->toIso8601String() : null,
            ],
            'meta' => [
                'source' => 'tool.upsert_assessment',
            ],
            'occurred_at' => now(),
        ]);
        $assessment->save();

        if (! $assessment->relatedOne(Order::class, 'order')) {
            $assessment->attachRelation($order, 'order');
        }
        if (! $assessment->relatedOne(Thread::class, 'primary')) {
            $assessment->attachRelation($this->thread, 'primary');
        }
        if (! $assessment->relatedOne(ServiceRequest::class, 'request')) {
            $assessment->attachRelation($this->serviceRequest, 'request');
        }

        $order->forceFill([
            'status' => $status === 'acknowledged' ? 'assessment_acknowledged' : 'assessment_pending_ack',
        ])->save();

        $this->thread->messages()->create([
            'senderable_type' => null,
            'senderable_id' => null,
            'type' => 'system',
            'tag' => 'assessment_upserted',
            'body' => "Assessment #{$assessment->id} saved with status {$assessment->status}.",
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
            'notes' => $schema->string()->required(),
            'status' => $schema->string()->enum(['submitted', 'revision_requested', 'acknowledged']),
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
