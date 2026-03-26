<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ThreadRelation extends Model
{
    use HasFactory;

    public const TypeRelatedTo = 'related_to';

    public const TypeReferences = 'references';

    public const TypeDependsOn = 'depends_on';

    public const TypeBlocks = 'blocks';

    public const TypeDerivedFrom = 'derived_from';

    public const TypeChildOf = 'child_of';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'relationable_type',
        'relationable_id',
        'type',
        'purpose',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function relationable(): MorphTo
    {
        return $this->morphTo();
    }
}
