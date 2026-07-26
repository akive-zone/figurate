<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AgentConversationMessage extends Model
{
    protected $table = 'agent_conversation_messages';

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var bool
     */
    public $incrementing = false;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id', 'id');
    }

    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    public function rootPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'root_post_id');
    }

    public function outputPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'output_post_id');
    }
}
