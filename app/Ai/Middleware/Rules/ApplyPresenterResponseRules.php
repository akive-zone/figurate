<?php

namespace App\Ai\Middleware\Rules;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class ApplyPresenterResponseRules
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        return $next($prompt->append(implode("\n", [
            'Presenter response rules:',
            '- Keep responses concise and actionable.',
            '- Prefer one clear next step; at most three short bullets when listing options.',
            '- Never invent request/order IDs, statuses, or tool outcomes.',
            '- If a required detail is missing, ask one focused follow-up question.',
        ])));
    }
}
