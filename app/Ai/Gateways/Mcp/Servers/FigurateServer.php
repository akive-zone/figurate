<?php

namespace App\Ai\Gateways\Mcp\Servers;

use App\Ai\Gateways\Mcp\Prompts\PlanChannelWorkPrompt;
use App\Ai\Gateways\Mcp\Prompts\SummarizeThreadPrompt;
use App\Ai\Gateways\Mcp\Resources\ChannelResource;
use App\Ai\Gateways\Mcp\Resources\FigurateServerGuideResource;
use App\Ai\Gateways\Mcp\Resources\ThreadResource;
use App\Ai\Gateways\Mcp\Tools\AssignThreadActorTool;
use App\Ai\Gateways\Mcp\Tools\CreatePostTool;
use App\Ai\Gateways\Mcp\Tools\CreateThreadTool;
use App\Ai\Gateways\Mcp\Tools\ListChannelsTool;
use App\Ai\Gateways\Mcp\Tools\ListPostsTool;
use App\Ai\Gateways\Mcp\Tools\ListThreadActorsTool;
use App\Ai\Gateways\Mcp\Tools\ListThreadsTool;
use App\Ai\Gateways\Mcp\Tools\ReadChannelTool;
use App\Ai\Gateways\Mcp\Tools\ReadThreadTool;
use App\Ai\Gateways\Mcp\Tools\SearchConversationContextTool;
use App\Ai\Gateways\Mcp\Tools\TransferThreadSessionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Figurate Server')]
#[Version('0.0.1')]
#[Instructions('Use this server to inspect and operate on Figurate chat context. It exposes channels, threads, posts, actors, and context search. Prefer read tools first, then use create_thread or create_post for safe workflow actions. This server intentionally excludes fulfillment-state transitions for now.')]
class FigurateServer extends Server
{
    protected array $tools = [
        ListChannelsTool::class,
        ReadChannelTool::class,
        ListThreadsTool::class,
        ReadThreadTool::class,
        ListPostsTool::class,
        ListThreadActorsTool::class,
        SearchConversationContextTool::class,
        AssignThreadActorTool::class,
        TransferThreadSessionTool::class,
        CreateThreadTool::class,
        CreatePostTool::class,
    ];

    protected array $resources = [
        FigurateServerGuideResource::class,
        ChannelResource::class,
        ThreadResource::class,
    ];

    protected array $prompts = [
        PlanChannelWorkPrompt::class,
        SummarizeThreadPrompt::class,
    ];
}
