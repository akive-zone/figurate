<?php

namespace App\Ai\Support;

use App\Ai\Support\A2a\A2aRegistry;
use App\Ai\Support\Acp\AcpRegistry;
use App\Ai\Support\Knowledge\KnowledgeSearchStoreResolver;
use App\Ai\Support\Mcp\McpRegistry;
use App\Ai\Tools\AutoModeSelectorTool;
use App\Ai\Tools\ContextBudgetTool;
use App\Ai\Tools\ConversationAuditTool;
use App\Ai\Tools\CreatePostFromConversationTool;
use App\Ai\Tools\DelegateA2aTaskTool;
use App\Ai\Tools\DelegateAcpTaskTool;
use App\Ai\Tools\DiscoverSkillsTool;
use App\Ai\Tools\DualWriteDiffTool;
use App\Ai\Tools\GetSubAgentInvocationContextTool;
use App\Ai\Tools\InvokeA2aAgentTool;
use App\Ai\Tools\InvokeAcpAgentTool;
use App\Ai\Tools\InvokeMcpTool;
use App\Ai\Tools\InvokeSubAgentTool;
use App\Ai\Tools\ListAvailableA2aAgentsTool;
use App\Ai\Tools\ListAvailableAcpAgentsTool;
use App\Ai\Tools\ListAvailableMcpToolsTool;
use App\Ai\Tools\ListAvailableSubAgentsTool;
use App\Ai\Tools\ModePolicyTool;
use App\Ai\Tools\PrivacyGuardTool;
use App\Ai\Tools\ReplayTool;
use App\Ai\Tools\SessionForkTool;
use App\Ai\Tools\SessionHealthTool;
use App\Ai\Tools\SessionMergeSummaryTool;
use App\Ai\Tools\SessionResetTool;
use App\Ai\Tools\SessionTransferTool;
use App\Ai\Tools\WriteMemoryFileTool;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\FileSearch;

class ToolResolver
{
    public function __construct(
        protected KnowledgeSearchStoreResolver $knowledgeSearchStoreResolver = new KnowledgeSearchStoreResolver,
        protected AcpRegistry $acpRegistry = new AcpRegistry,
        protected A2aRegistry $a2aRegistry = new A2aRegistry,
        protected McpRegistry $mcpRegistry = new McpRegistry,
    ) {}

    /**
     * @return list<Tool>
     */
    public function resolve(Thread $thread, User $user, ?ThreadActor $threadActor = null): array
    {
        $sharedTools = $this->sharedTools($thread, $user, $threadActor);
        $threadable = $thread->threadable;

        if ($threadable instanceof Space) {
            $sharedTools[] = new CreatePostFromConversationTool($thread, $threadable, $user);
        }

        return $sharedTools;
    }

    /**
     * @return list<Tool>
     */
    protected function sharedTools(Thread $thread, User $user, ?ThreadActor $threadActor = null): array
    {
        return [new AutoModeSelectorTool($thread, $user),
            new ConversationAuditTool($thread, $user),
            new SessionResetTool($thread, $user),
            new SessionForkTool($thread, $user),
            new SessionTransferTool($thread, $user),
            new SessionMergeSummaryTool($thread, $user),
            new ModePolicyTool($thread, $user),
            new ContextBudgetTool($thread, $user),
            new DiscoverSkillsTool,
            new GetSubAgentInvocationContextTool($thread, $user, $threadActor),
            new ListAvailableSubAgentsTool,
            new InvokeSubAgentTool($thread, $user, $threadActor),
            ...$this->mcpTools($thread, $user, $threadActor),
            ...$this->acpTools($thread, $user, $threadActor),
            ...$this->a2aTools($thread, $user, $threadActor),
            new DualWriteDiffTool($thread, $user),
            new ReplayTool($thread, $user),
            new PrivacyGuardTool($thread, $user),
            new SessionHealthTool($thread, $user),
            new WriteMemoryFileTool($thread, $user),
            ...$this->knowledgeSearchTools($thread),
        ];
    }

    /**
     * @return list<Tool>
     */
    protected function knowledgeSearchTools(Thread $thread): array
    {
        $storeIds = $this->knowledgeSearchStoreResolver->resolveExternalStoreIds($thread);

        if ($storeIds === []) {
            return [];
        }

        return [
            new FileSearch(stores: $storeIds),
        ];
    }

    /**
     * @return list<Tool>
     */
    protected function mcpTools(Thread $thread, User $user, ?ThreadActor $threadActor = null): array
    {
        if (! $this->mcpRegistry->enabled($thread, $user)) {
            return [];
        }

        return [
            new ListAvailableMcpToolsTool($thread, $user),
            new InvokeMcpTool($thread, $user, $threadActor),
        ];
    }

    /**
     * @return list<Tool>
     */
    protected function acpTools(Thread $thread, User $user, ?ThreadActor $threadActor = null): array
    {
        if (! $this->acpRegistry->enabled($thread, $user)) {
            return [];
        }

        return [
            new ListAvailableAcpAgentsTool($thread, $user),
            new InvokeAcpAgentTool($thread, $user, $threadActor),
            new DelegateAcpTaskTool($thread, $user, $threadActor),
        ];
    }

    /**
     * @return list<Tool>
     */
    protected function a2aTools(Thread $thread, User $user, ?ThreadActor $threadActor = null): array
    {
        if (! $this->a2aRegistry->enabled($thread, $user)) {
            return [];
        }

        return [
            new ListAvailableA2aAgentsTool($thread, $user),
            new InvokeA2aAgentTool($thread, $user, $threadActor),
            new DelegateA2aTaskTool($thread, $user, $threadActor),
        ];
    }
}
