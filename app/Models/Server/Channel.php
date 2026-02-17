<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Channel extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'status',
    ];

    public function actorStates(): HasMany
    {
        return $this->hasMany(ChannelActorState::class);
    }

    public function threads(): MorphMany
    {
        return $this->morphMany(Thread::class, 'threadable');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ChannelRelation::class);
    }

    public function requests(): MorphToMany
    {
        return $this->morphedByMany(
            Request::class,
            'relationable',
            'channel_relations',
            'channel_id',
            'relationable_id'
        )->withTimestamps();
    }

    public function posts(): MorphMany
    {
        return $this->morphMany(Post::class, 'postable');
    }

    public function hasActor(User $user): bool
    {
        return $this->actorStates()
            ->where('actor_type', $user->getMorphClass())
            ->where('actor_id', $user->getKey())
            ->exists();
    }

    public function primaryRequest(): ?Request
    {
        return $this->requests()->latest('id')->first();
    }

    /**
     * @return Collection<int, Thread>
     */
    public function conversationThreads(): Collection
    {
        $requestRecord = $this->primaryRequest();

        return ($requestRecord ? $requestRecord->threads() : $this->threads())
            ->with(['messages', 'posts'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Message>
     */
    public function conversationRequestMessages(): Collection
    {
        $requestRecord = $this->primaryRequest();

        if (! $requestRecord) {
            return collect();
        }

        return $requestRecord->messages()
            ->orderBy('created_at')
            ->get();
    }

    public function latestConversationMessage(): ?Message
    {
        $candidateMessages = collect();
        $requestRecord = $this->primaryRequest();

        if ($requestRecord) {
            $latestRequestMessage = $requestRecord->messages()->latest('created_at')->first();
            if ($latestRequestMessage instanceof Message) {
                $candidateMessages->push($latestRequestMessage);
            }
        }

        $latestThreadMessage = $this->conversationThreads()
            ->flatMap(fn (Thread $thread) => $thread->messages)
            ->sortByDesc('created_at')
            ->first();

        if ($latestThreadMessage instanceof Message) {
            $candidateMessages->push($latestThreadMessage);
        }

        /** @var Message|null $latestMessage */
        $latestMessage = $candidateMessages->sortByDesc('created_at')->first();

        return $latestMessage;
    }
}
