<?php

namespace App\Ai\Support\Knowledge;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MessageAttachmentStoreIngestor
{
    public function __construct(
        protected KnowledgeStoreManager $storeManager,
        protected StoreDocumentIndexer $documentIndexer,
    ) {}

    /**
     * @return array{store_id: int, processed: int, indexed: int, failed: int, local_only: int}
     */
    public function ingest(Thread $thread, Message $message, User $actor): array
    {
        $store = $this->storeManager->forThread($thread, $actor);

        $result = [
            'store_id' => $store->id,
            'processed' => 0,
            'indexed' => 0,
            'failed' => 0,
            'local_only' => 0,
        ];

        $message->getMedia('attachments')
            ->each(function (Media $media) use ($thread, $store, $message, &$result): void {
                $result['processed']++;

                $document = $this->documentIndexer->indexMedia(
                    store: $store,
                    media: $media,
                    message: $message,
                    origin: 'message_attachment',
                    metadata: [
                        'thread_id' => $thread->id,
                        'message_id' => $message->id,
                    ],
                );

                if ($document->status === 'indexed') {
                    $result['indexed']++;
                }

                if ($document->status === 'local_only') {
                    $result['local_only']++;
                }

                if ($document->status === 'index_failed') {
                    $result['failed']++;
                }
            });

        return $result;
    }
}
