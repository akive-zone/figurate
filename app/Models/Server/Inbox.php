<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Inbox extends Model
{
    /** @use HasFactory<\Database\Factories\Server\InboxFactory> */
    use HasFactory, HasPublicUuid;

    public const KindThread = 'thread';

    public const KindThreadMessage = 'thread_message';

    public const KindThreadEvent = 'thread_event';

    public const StatusUnread = 'unread';

    public const StatusRead = 'read';

    public const StatusArchived = 'archived';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'thread_id',
        'inboxable_type',
        'inboxable_id',
        'kind',
        'status',
        'title',
        'summary',
        'source',
        'payload',
        'read_at',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function inboxable(): MorphTo
    {
        return $this->morphTo();
    }
}
