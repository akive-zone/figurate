<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SpaceRelation extends Model
{
    use HasFactory, HasUlids;

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
        'ulid',
        'space_id',
        'relationable_type',
        'relationable_id',
        'type',
        'purpose',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function relationable(): MorphTo
    {
        return $this->morphTo();
    }
}
