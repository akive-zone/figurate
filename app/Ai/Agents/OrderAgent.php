<?php

namespace App\Ai\Agents;

use App\Models\Server\Request;
use App\Models\Server\Thread;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

class OrderAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public ?Thread $thread = null) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $request = $this->thread?->threadable;

        if (! $request instanceof Request) {
            $request = null;
        }
        $order = $request?->order;

        if (! $request) {
            return 'You are OrderAgent. Help askers navigate booked orders and fulfillment.';
        }

        $orderContext = $order
            ? "- Order #{$order->id} status: {$order->status}"
            : '- No order exists yet.';

        return "You are OrderAgent for the Signal asker flow.\n".
            "Current fulfillment context:\n".
            "- Request #{$request->id} status: {$request->status}\n".
            "{$orderContext}\n\n".
            'Your role:\n'.
            '- Guide the asker through booked-order fulfillment.\n'.
            '- Highlight pending confirmations and milestones.\n'.
            '- Keep responses direct and operational.';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }
}
