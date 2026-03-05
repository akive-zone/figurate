<?php

namespace App\Ai\Tools;

use App\Ai\Agents\ObserverAgent;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;
use Throwable;

class SafetyGuardObserver implements Tool
{
    /**
     * @var list<string>
     */
    protected array $blockedKeywords = [
        'credit card',
        'card number',
        'cvv',
        'otp',
        'one time password',
        'social security number',
    ];

    /**
     * @var list<string>
     */
    protected array $flaggedKeywords = [
        'whatsapp',
        'telegram',
        'pay outside',
        'off platform',
        'bank transfer',
        'send money directly',
    ];

    public function __construct(
        protected Thread $thread,
        protected Message $message,
        protected ThreadActor $threadActor,
    ) {}

    public function description(): Stringable|string
    {
        return 'Assess the message for safety risks and return normalized observer output.';
    }

    public function handle(ToolRequest $request): Stringable|string
    {
        try {
            $response = ObserverAgent::make(thread: $this->thread, message: $this->message)
                ->prompt($this->buildPrompt($request));

            return json_encode(
                $this->normalizeAgentResponse(is_array($response) ? $response : []),
                JSON_UNESCAPED_SLASHES
            ) ?: $this->encodeAllow();
        } catch (Throwable) {
            return json_encode(
                $this->fallbackKeywordRules($this->message),
                JSON_UNESCAPED_SLASHES
            ) ?: $this->encodeAllow();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'message_id' => $schema->integer(),
            'message_body' => $schema->string(),
            'attachments' => $schema->array($schema->string()),
        ];
    }

    protected function buildPrompt(ToolRequest $request): string
    {
        $attachments = collect($this->message->attachments ?? [])
            ->map(fn (mixed $item): string => is_array($item) ? ($item['name'] ?? 'file') : 'file')
            ->values()
            ->all();

        return json_encode([
            'thread' => [
                'id' => $this->thread->id,
                'purpose' => $this->thread->purpose,
                'phase' => $this->thread->phase,
            ],
            'observer_actor' => $this->threadActor->actorReference(),
            'message' => [
                'id' => $request->integer('message_id') ?: $this->message->id,
                'body' => $request->string('message_body')->toString() ?: $this->message->text,
                'attachments' => $request->array('attachments') ?: $attachments,
            ],
        ], JSON_PRETTY_PRINT) ?: (string) $this->message->text;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function normalizeAgentResponse(array $response): array
    {
        $action = mb_strtolower(trim((string) ($response['action'] ?? 'allow')));
        $reason = trim((string) ($response['reason'] ?? ''));
        $suggestion = trim((string) ($response['suggestion'] ?? ''));
        $severity = $this->normalizeSeverity(
            mb_strtolower(trim((string) ($response['severity'] ?? 'low'))),
            'low',
        );

        return match ($action) {
            'block' => [
                'event_type' => 'message_blocked',
                'severity' => $this->normalizeSeverity($severity, 'high'),
                'payload' => ['reason' => $reason, 'source' => 'observer_agent'],
                'redact_message' => true,
            ],
            'flag' => [
                'event_type' => 'moderation_flagged',
                'severity' => $this->normalizeSeverity($severity, 'medium'),
                'payload' => ['reason' => $reason, 'source' => 'observer_agent'],
                'redact_message' => false,
            ],
            'suggest' => [
                'event_type' => 'suggestion_created',
                'severity' => $this->normalizeSeverity($severity, 'low'),
                'payload' => [
                    'reason' => $reason,
                    'suggestion' => $suggestion,
                    'source' => 'observer_agent',
                ],
                'redact_message' => false,
            ],
            default => [
                'event_type' => null,
                'severity' => 'low',
                'payload' => ['source' => 'observer_agent'],
                'redact_message' => false,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function fallbackKeywordRules(Message $message): array
    {
        $content = mb_strtolower($message->text);

        foreach ($this->blockedKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return [
                    'event_type' => 'message_blocked',
                    'severity' => 'high',
                    'payload' => ['reason' => 'sensitive_term', 'term' => $keyword, 'source' => 'fallback_keyword'],
                    'redact_message' => true,
                ];
            }
        }

        foreach ($this->flaggedKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return [
                    'event_type' => 'moderation_flagged',
                    'severity' => 'medium',
                    'payload' => ['reason' => 'risky_term', 'term' => $keyword, 'source' => 'fallback_keyword'],
                    'redact_message' => false,
                ];
            }
        }

        return [
            'event_type' => null,
            'severity' => 'low',
            'payload' => ['source' => 'fallback_keyword'],
            'redact_message' => false,
        ];
    }

    protected function normalizeSeverity(string $severity, string $default): string
    {
        return in_array($severity, ['low', 'medium', 'high'], true) ? $severity : $default;
    }

    protected function encodeAllow(): string
    {
        return '{"event_type":null,"severity":"low","payload":{"source":"observer_tool"},"redact_message":false}';
    }
}
