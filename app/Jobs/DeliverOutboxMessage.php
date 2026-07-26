<?php

namespace App\Jobs;

use App\Features\Actions\Chat\ProtocolRegistry;
use App\Models\Server\Outbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeliverOutboxMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [2, 10, 30, 120];
    }

    public function __construct(public int $outboxId)
    {
        $this->afterCommit();
    }

    public function handle(ProtocolRegistry $protocolRegistry): void
    {
        $outbox = Outbox::query()->find($this->outboxId);
        if (! $outbox || $outbox->direction !== Outbox::DirectionOutbound) {
            return;
        }

        if (! in_array($outbox->status, [Outbox::StatusPending, Outbox::StatusProcessing], true)) {
            return;
        }

        $outbox->forceFill([
            'status' => Outbox::StatusProcessing,
            'attempts' => (int) $outbox->attempts + 1,
        ])->save();

        try {
            $sender = $protocolRegistry->outboundSender($outbox->protocol);

            if (! $sender) {
                throw new \RuntimeException("Unsupported outbox protocol [{$outbox->protocol}]");
            }

            $result = $sender->send($outbox);

            $outbox->forceFill([
                'status' => Outbox::StatusDelivered,
                'processed_at' => now(),
                'result' => $result,
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $outbox->forceFill([
                'status' => Outbox::StatusFailed,
                'failed_at' => now(),
                'error_message' => mb_substr(trim($exception->getMessage()), 0, 2000),
            ])->save();

            throw $exception;
        }
    }
}
