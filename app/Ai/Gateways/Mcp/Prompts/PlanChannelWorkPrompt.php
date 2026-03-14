<?php

namespace App\Ai\Gateways\Mcp\Prompts;

use App\Ai\Gateways\Mcp\Support\FigurateMcpPayloads;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Guide an agent to inspect a channel and propose the next work plan.')]
class PlanChannelWorkPrompt extends Prompt
{
    public function handle(Request $request, FigurateMcpPayloads $payloads): Response
    {
        $channelId = trim((string) $request->get('channel_id'));
        $objective = trim((string) $request->get('objective', 'Determine the next best work steps for this channel.'));
        abort_if($channelId === '', 422, 'channel_id is required.');

        $actor = $payloads->actor($request);
        $channel = $payloads->resolveChannel($actor, $channelId);

        return Response::text(implode("\n", [
            "You are planning work inside Figurate channel {$channel->uuid}.",
            "Objective: {$objective}",
            '',
            'Use this workflow:',
            '1. Read the channel context and active threads.',
            '2. Identify the current workstream, missing information, and blockers.',
            '3. Search conversation context for prior decisions before proposing any action.',
            '4. If a new workstream is needed, create a thread.',
            '5. If a durable summary or decision should be recorded, create a post.',
            '',
            'Output format:',
            '- Current state',
            '- Missing information',
            '- Recommended next actions',
            '- Whether a new thread or post should be created',
        ]));
    }

    public function arguments(): array
    {
        return [
            new Argument('channel_id', 'The channel UUID to inspect.', true),
            new Argument('objective', 'The planning objective or question to answer.', false),
        ];
    }
}
