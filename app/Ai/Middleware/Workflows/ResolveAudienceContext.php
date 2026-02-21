<?php

namespace App\Ai\Middleware\Workflows;

use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class ResolveAudienceContext
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $thread = $this->resolveThread($prompt);
        $actor = $this->resolveActor($prompt);

        if (! $thread || ! $actor) {
            return $next($prompt);
        }

        [$channel, $request] = $this->resolveChannelAndRequest($thread);
        $speakerParty = $this->resolveSpeakerParty($actor, $request);
        $participantCount = $this->resolveParticipantCount($channel, $request);
        $conversationMode = $participantCount > 2 ? 'group' : 'direct';
        $audience = $this->resolveAudience($speakerParty, $conversationMode);

        $context = implode("\n", [
            'Audience context (system policy):',
            '- Speaker role: member',
            "- Speaker party: {$speakerParty}",
            "- Audience: {$audience}",
            "- Conversation mode: {$conversationMode}",
            "- Participant count: {$participantCount}",
            '- Adapt reply to the audience and avoid cross-party leakage.',
            '- Keep party terms consistent: asker, worker, group.',
        ]);

        return $next($prompt->prepend($context));
    }

    protected function resolveThread(AgentPrompt $prompt): ?Thread
    {
        $agent = $prompt->agent;

        if (! property_exists($agent, 'thread')) {
            return null;
        }

        $thread = $agent->thread;

        return $thread instanceof Thread ? $thread : null;
    }

    protected function resolveActor(AgentPrompt $prompt): ?User
    {
        $agent = $prompt->agent;

        if (! property_exists($agent, 'actor')) {
            return null;
        }

        $actor = $agent->actor;

        return $actor instanceof User ? $actor : null;
    }

    /**
     * @return array{0: ?Channel, 1: ?ServiceRequest}
     */
    protected function resolveChannelAndRequest(Thread $thread): array
    {
        $threadable = $thread->threadable;

        if ($threadable instanceof Channel) {
            return [$threadable, $threadable->primaryRequest()];
        }

        if ($threadable instanceof ServiceRequest) {
            return [$threadable->channels()->latest('channels.id')->first(), $threadable];
        }

        return [null, null];
    }

    protected function resolveSpeakerParty(User $actor, ?ServiceRequest $request): string
    {
        if (! $request) {
            return 'member';
        }

        if ($request->hasUserActor($actor, ServiceRequest::ActionAsker)) {
            return 'asker';
        }

        if ($request->hasProfileActorForUser($actor, ServiceRequest::ActionTargetProfile)) {
            return 'worker';
        }

        $order = $request->currentOrder();
        $sellerProfile = $order?->sellerProfileRecord();

        if ($sellerProfile && $sellerProfile->user_id === $actor->id) {
            return 'worker';
        }

        if ($request->hasParticipant($actor)) {
            return 'member';
        }

        return 'external';
    }

    protected function resolveParticipantCount(?Channel $channel, ?ServiceRequest $request): int
    {
        if ($channel) {
            return max(
                1,
                (int) $channel->actorStates()
                    ->where('status', 'active')
                    ->where('actorable_type', (new User)->getMorphClass())
                    ->distinct('actorable_id')
                    ->count('actorable_id')
            );
        }

        if ($request) {
            $askerCount = $request->users()
                ->wherePivot('action', ServiceRequest::ActionAsker)
                ->distinct('users.id')
                ->count('users.id');
            $workerCount = $request->profiles()
                ->wherePivot('action', ServiceRequest::ActionTargetProfile)
                ->distinct('profiles.user_id')
                ->count('profiles.user_id');

            return max(1, $askerCount + $workerCount);
        }

        return 1;
    }

    protected function resolveAudience(string $speakerParty, string $conversationMode): string
    {
        if ($conversationMode === 'group') {
            return 'group';
        }

        return match ($speakerParty) {
            'asker' => 'worker',
            'worker' => 'asker',
            default => 'member',
        };
    }
}
