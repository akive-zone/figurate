<?php

namespace App\Ai\Middleware\Rules;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class ResponseQualityGate
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        return $next($prompt->append(implode("\n", [
            'Response quality gate:',
            '- Ensure the answer is concise, operational, and aligned to current fulfillment stage.',
            '- Include one clear next step when actionable.',
            '- Avoid filler, repetition, and conflicting instructions.',
        ])));
    }
}
