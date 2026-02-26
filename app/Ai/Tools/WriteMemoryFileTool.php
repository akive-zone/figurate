<?php

namespace App\Ai\Tools;

use App\Ai\Support\Knowledge\KnowledgeStoreManager;
use App\Ai\Support\Knowledge\StoreDocumentIndexer;
use App\Ai\Support\ThreadContextResolver;
use App\Ai\Tools\Diagnostics\EncodesToolResponse;
use App\Models\Server\Channel;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class WriteMemoryFileTool implements Tool
{
    use EncodesToolResponse;

    public function __construct(
        protected Thread $thread,
        protected User $actor,
        protected KnowledgeStoreManager $storeManager = new KnowledgeStoreManager,
        protected StoreDocumentIndexer $documentIndexer = new StoreDocumentIndexer,
        protected ThreadContextResolver $threadContextResolver = new ThreadContextResolver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Write text content into conversation memory as a file and index it into the active knowledge store.';
    }

    public function handle(ToolRequest $request): Stringable|string
    {
        $content = trim((string) ($request['content'] ?? ''));
        if ($content === '') {
            return $this->error('content is required.');
        }

        $filename = trim((string) ($request['filename'] ?? 'memory-note.txt'));
        $filename = $filename === '' ? 'memory-note.txt' : $filename;
        $target = (string) ($request['target'] ?? 'thread');
        $scope = trim((string) ($request['scope'] ?? 'memory'));
        $scope = $scope === '' ? 'memory' : $scope;

        if ($target === 'channel') {
            $channel = $this->threadContextResolver->resolveChannel($this->thread);
            if (! $channel instanceof Channel) {
                return $this->error('No channel context available for channel-scoped memory.');
            }

            $store = $this->storeManager->forChannel($channel, $this->actor, $scope);
        } else {
            $store = $this->storeManager->forThread($this->thread, $this->actor, $scope);
        }

        $media = $store->addMediaFromString($content)
            ->usingName(pathinfo($filename, PATHINFO_FILENAME))
            ->usingFileName($filename)
            ->toMediaCollection('documents');

        $document = $this->documentIndexer->indexMedia(
            store: $store,
            media: $media,
            message: null,
            origin: 'agent_memory_tool',
            metadata: [
                'thread_id' => $this->thread->id,
                'target' => $target,
            ],
        );

        return $this->ok([
            'store_id' => $store->id,
            'store_external_id' => $store->external_store_id,
            'media_id' => $media->id,
            'document_id' => $document->id,
            'document_status' => $document->status,
            'provider_file_id' => $document->provider_file_id,
            'provider_document_id' => $document->provider_document_id,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string(),
            'filename' => $schema->string(),
            'scope' => $schema->string(),
            'target' => $schema->string(),
        ];
    }
}
