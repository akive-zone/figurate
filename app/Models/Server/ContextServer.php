<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContextServer extends Model
{
    /** @use HasFactory<\Database\Factories\ContextServerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contextable_type',
        'contextable_id',
        'server',
        'label',
        'enabled',
        'priority',
        'transport',
        'endpoint_url',
        'handler',
        'allowed_tools',
        'auth_type',
        'credentials',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
            'allowed_tools' => 'array',
            'transport' => 'string',
            'credentials' => 'encrypted:array',
            'meta' => 'encrypted:array',
        ];
    }

    public function contextable(): MorphTo
    {
        return $this->morphTo();
    }
}
