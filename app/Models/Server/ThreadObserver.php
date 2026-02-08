<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadObserver extends Model
{
    /** @use HasFactory<\Database\Factories\ThreadObserverFactory> */
    use HasFactory;

    public const SafetyGuard = 'safety_guard';

    public const ModePassive = 'passive';

    public const ModeEnforcing = 'enforcing';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'observer_key',
        'mode',
        'status',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
