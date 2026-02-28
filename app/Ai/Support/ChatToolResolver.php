<?php

namespace App\Ai\Support;

use App\Ai\Support\Knowledge\KnowledgeSearchStoreResolver;
use App\Ai\Tools\AutoModeSelectorTool;
use App\Ai\Tools\ContextBudgetTool;
use App\Ai\Tools\ConversationAuditTool;
use App\Ai\Tools\CreatePostFromConversationTool;
use App\Ai\Tools\DiscoverSkillsTool;
use App\Ai\Tools\DualWriteDiffTool;
use App\Ai\Tools\InvokeMcpTool;
use App\Ai\Tools\ListAvailableMcpToolsTool;
use App\Ai\Tools\ModePolicyTool;
use App\Ai\Tools\PrivacyGuardTool;
use App\Ai\Tools\ReplayTool;
use App\Ai\Tools\SessionForkTool;
use App\Ai\Tools\SessionHealthTool;
use App\Ai\Tools\SessionMergeSummaryTool;
use App\Ai\Tools\SessionResetTool;
use App\Ai\Tools\SessionTransferTool;
use App\Ai\Tools\WriteMemoryFileTool;
use App\Models\Server\Channel;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\FileSearch;

class ChatToolResolver
{
    public function __construct(
        protected KnowledgeSearchStoreResolver $knowledgeSearchStoreResolver = new KnowledgeSearchStoreResolver,
    ) {}

    /**
     * @return list<Tool>
     */
    public function resolve(Thread $thread, User $user): array
    {
        $sharedTools = $this->sharedTools($thread, $user);
        $threadable = $thread->threadable;

        if ($threadable instanceof Channel) {
            $sharedTools[] = new CreatePostFromConversationTool($thread, $threadable, $user);
        }

        return $sharedTools;
    }

    /**
     * @return list<Tool>
     */
    protected function sharedTools(Thread $thread, User $user): array
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
            ...$this->mcpTools($thread, $user),
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
    protected function mcpTools(Thread $thread, User $user): array
    {
        if (! ((bool) config('services.mcp.enabled', false))) {
            return [];
        }

        return [
            new ListAvailableMcpToolsTool($thread, $user),
            new InvokeMcpTool($thread, $user),
        ];
    }
}
