<?php

namespace App\Ai\Gateways\Mcp\Servers;

use App\Ai\Gateways\Mcp\Prompts\PlanSpaceWorkPrompt;
use App\Ai\Gateways\Mcp\Prompts\SummarizeThreadPrompt;
use App\Ai\Gateways\Mcp\Resources\FigurateServerGuideResource;
use App\Ai\Gateways\Mcp\Resources\SpaceResource;
use App\Ai\Gateways\Mcp\Resources\ThreadResource;
use App\Ai\Gateways\Mcp\Tools\AssignThreadActorTool;
use App\Ai\Gateways\Mcp\Tools\CreateGraphEdgeTool;
use App\Ai\Gateways\Mcp\Tools\CreatePostTool;
use App\Ai\Gateways\Mcp\Tools\CreateThreadTool;
use App\Ai\Gateways\Mcp\Tools\ListPostsTool;
use App\Ai\Gateways\Mcp\Tools\ListSpacesTool;
use App\Ai\Gateways\Mcp\Tools\ListThreadActorsTool;
use App\Ai\Gateways\Mcp\Tools\ListThreadsTool;
use App\Ai\Gateways\Mcp\Tools\QueryGraphEdgesTool;
use App\Ai\Gateways\Mcp\Tools\ReadSpaceTool;
use App\Ai\Gateways\Mcp\Tools\ReadThreadTool;
use App\Ai\Gateways\Mcp\Tools\SearchConversationContextTool;
use App\Ai\Gateways\Mcp\Tools\TransferThreadSessionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Figurate Server')]
#[Version('0.0.1')]
#[Instructions('Use this server to inspect and operate on Figurate chat context. It exposes spaces, threads, posts, actors, and context search. Prefer read tools first, then use create_thread or create_post for safe workflow actions. Application-specific state transitions remain outside the core server.')]
class FigurateServer extends Server
{
    protected array $tools = [
        ListSpacesTool::class,
        ReadSpaceTool::class,
        ListThreadsTool::class,
        ReadThreadTool::class,
        ListPostsTool::class,
        ListThreadActorsTool::class,
        SearchConversationContextTool::class,
        QueryGraphEdgesTool::class,
        AssignThreadActorTool::class,
        TransferThreadSessionTool::class,
        CreateGraphEdgeTool::class,
        CreateThreadTool::class,
        CreatePostTool::class,
    ];

    protected array $resources = [
        FigurateServerGuideResource::class,
        SpaceResource::class,
        ThreadResource::class,
    ];

    protected array $prompts = [
        PlanSpaceWorkPrompt::class,
        SummarizeThreadPrompt::class,
    ];
}
