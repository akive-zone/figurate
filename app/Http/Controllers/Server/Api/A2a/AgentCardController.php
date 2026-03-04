<?php

namespace App\Http\Controllers\Server\Api\A2a;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AgentCardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'id' => config('a2a.inbound.agent.id'),
            'name' => config('a2a.inbound.agent.name'),
            'description' => config('a2a.inbound.agent.description'),
            'version' => config('a2a.inbound.agent.version'),
            'protocol' => [
                'name' => 'a2a',
                'version' => (string) config('a2a.inbound.protocol.version', 'latest'),
                'transport' => ['jsonrpc', 'sse'],
            ],
            'capabilities' => [
                'streaming' => (bool) config('a2a.inbound.capabilities.streaming', true),
                'push_notifications' => (bool) config('a2a.inbound.capabilities.push_notifications', false),
                'pushNotifications' => (bool) config('a2a.inbound.capabilities.push_notifications', false),
                'history' => (bool) config('a2a.inbound.capabilities.history', true),
            ],
            'endpoints' => [
                'rpc' => '/api/a2a/rpc',
                'stream' => '/api/a2a/stream',
                'tasks' => '/api/a2a/rpc',
            ],
            'security' => [
                'mode' => 'scaffold',
            ],
        ]);
    }
}
