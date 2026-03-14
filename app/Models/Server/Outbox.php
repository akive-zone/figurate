<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outbox extends Model
{
    /** @use HasFactory<\Database\Factories\OutboxFactory> */
    use HasFactory;

    public const DirectionOutbound = 'outbound';

    public const DirectionInbound = 'inbound';

    public const StatusPending = 'pending';

    public const StatusProcessing = 'processing';

    public const StatusDelivered = 'delivered';

    public const StatusReceived = 'received';

    public const StatusFailed = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'message_id',
        'direction',
        'protocol',
        'provider',
        'target',
        'status',
        'attempts',
        'available_at',
        'processed_at',
        'failed_at',
        'idempotency_key',
        'payload',
        'result',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'payload' => 'array',
            'result' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
