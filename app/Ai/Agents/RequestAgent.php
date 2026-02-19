<?php

namespace App\Ai\Agents;

use App\Ai\Support\ThreadToolResolver;
use App\Models\Server\Request;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class RequestAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public ?Thread $thread = null,
        public ?User $actor = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $request = $this->thread?->threadable;

        if (! $request instanceof Request) {
            $request = null;
        }

        if (! $request) {
            return "You are a presenter agent for intake.\n".
                "Goal:\n".
                "- First call your flow tool to understand current channel state.\n".
                "- Get just enough information, then create the request using your tool.\n".
                "- Do not ask repetitive or low-value follow-up questions.\n".
                "- If intent is clear from the user's message, call the request-creation tool immediately.\n".
                '- After creating it, confirm what was created in one concise response.';
        }

        return "You are presenter agent for the asker flow.\n".
            "Current request context:\n".
            "- Request #{$request->id}\n".
            "- Title: {$request->title}\n".
            "- Description: {$request->description}\n".
            "- Status: {$request->status}\n\n".
            'Your role:\n'.
            '- Use your flow tool to confirm the current stage before responding.\n'.
            '- Help the asker clarify needs and scope.\n'.
            '- Suggest up to 3 matching profile candidates for this request with short reasons.\n'.
            '- Suggest next fulfillment steps.\n'.
            '- Be concise and actionable.';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        if (! $this->thread || ! $this->actor) {
            return [];
        }

        return app(ThreadToolResolver::class)->resolve($this->thread, $this->actor);
    }
}
