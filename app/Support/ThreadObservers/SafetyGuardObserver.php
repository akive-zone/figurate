<?php

namespace App\Support\ThreadObservers;

use App\Ai\Agents\ThreadSafetyObserverAgent;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Support\ThreadObservers\Contracts\ThreadObserverContract;
use Throwable;

class SafetyGuardObserver implements ThreadObserverContract
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

    public function key(): string
    {
        return ThreadActor::ActorSafetyGuard;
    }

    public function observe(Thread $thread, Message $message): ?ObserverResult
    {
        try {
            $response = ThreadSafetyObserverAgent::make(thread: $thread, message: $message)
                ->prompt($this->buildPrompt($thread, $message));

            $action = mb_strtolower(trim((string) ($response['action'] ?? 'allow')));
            $reason = trim((string) ($response['reason'] ?? ''));
            $suggestion = trim((string) ($response['suggestion'] ?? ''));
            $severity = mb_strtolower(trim((string) ($response['severity'] ?? 'low')));

            return match ($action) {
                'block' => new ObserverResult(
                    eventType: 'message_blocked',
                    severity: $this->normalizeSeverity($severity, 'high'),
                    payload: ['reason' => $reason, 'source' => 'ai_observer'],
                    redactMessage: true,
                ),
                'flag' => new ObserverResult(
                    eventType: 'moderation_flagged',
                    severity: $this->normalizeSeverity($severity, 'medium'),
                    payload: ['reason' => $reason, 'source' => 'ai_observer'],
                ),
                'suggest' => new ObserverResult(
                    eventType: 'suggestion_created',
                    severity: $this->normalizeSeverity($severity, 'low'),
                    payload: [
                        'reason' => $reason,
                        'suggestion' => $suggestion,
                        'source' => 'ai_observer',
                    ],
                ),
                default => null,
            };
        } catch (Throwable) {
            return $this->fallbackKeywordRules($message);
        }
    }

    protected function buildPrompt(Thread $thread, Message $message): string
    {
        $attachments = collect($message->attachments ?? [])
            ->map(fn (mixed $item): string => is_array($item) ? ($item['name'] ?? 'file') : 'file')
            ->values()
            ->all();

        return json_encode([
            'thread_id' => $thread->id,
            'thread_phase' => $thread->phase,
            'thread_primary_actor' => $thread->primaryPresenterActor()?->actorName(),
            'message_id' => $message->id,
            'message_body' => $message->body,
            'attachments' => $attachments,
        ], JSON_PRETTY_PRINT) ?: $message->body;
    }

    protected function fallbackKeywordRules(Message $message): ?ObserverResult
    {
        $content = mb_strtolower($message->body);

        foreach ($this->blockedKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return new ObserverResult(
                    eventType: 'message_blocked',
                    severity: 'high',
                    payload: ['reason' => 'sensitive_term', 'term' => $keyword, 'source' => 'fallback_keyword'],
                    redactMessage: true,
                );
            }
        }

        foreach ($this->flaggedKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return new ObserverResult(
                    eventType: 'moderation_flagged',
                    severity: 'medium',
                    payload: ['reason' => 'risky_term', 'term' => $keyword, 'source' => 'fallback_keyword'],
                );
            }
        }

        return null;
    }

    protected function normalizeSeverity(string $severity, string $default): string
    {
        return in_array($severity, ['low', 'medium', 'high'], true) ? $severity : $default;
    }
}
