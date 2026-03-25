<?php

namespace App\Ai\Support\Knowledge;

use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;

class MessageAttachmentStoreIngestor
{
    public function __construct(
        protected KnowledgeStoreManager $storeManager,
        protected StoreDocumentIndexer $documentIndexer,
    ) {}

    /**
     * @return array{store_id: int, processed: int, indexed: int, failed: int, local_only: int}
     */
    public function ingest(Thread $thread, Post $post, User $actor): array
    {
        $store = $this->storeManager->forThread($thread, $actor);

        $result = [
            'store_id' => $store->id,
            'processed' => 0,
            'indexed' => 0,
            'failed' => 0,
            'local_only' => 0,
        ];

        $attachments = (array) data_get($post->data, 'attachments', []);

        foreach ($attachments as $attachment) {
            try {
                $document = $this->documentIndexer->indexPostAttachment(
                    store: $store,
                    post: $post,
                    attachment: $attachment,
                    origin: 'thread_message',
                );

                if ($document->status === 'indexed') {
                    $result['indexed']++;
                } elseif ($document->status === 'local_only') {
                    $result['local_only']++;
                }
            } catch (\Throwable $exception) {
                report($exception);
                $result['failed']++;
            }
            $result['processed']++;
        }

        return $result;
    }
}
