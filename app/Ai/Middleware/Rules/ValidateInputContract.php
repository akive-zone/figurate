<?php

namespace App\Ai\Middleware\Rules;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class ValidateInputContract
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        return $next($prompt->append(implode("\n", [
            'Input contract rules:',
            '- Treat user payload shape as body (text) and attachments (files).',
            '- Never assume legacy content/contents fields.',
            '- If body is empty and no attachment intent is present, request one concrete user input.',
        ])));
    }
}
