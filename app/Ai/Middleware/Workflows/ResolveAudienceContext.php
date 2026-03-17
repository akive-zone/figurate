<?php

namespace App\Ai\Middleware\Workflows;

use App\Ai\Support\ThreadContextResolver;
use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Closure;
use Figurate\FulfillmentManager\Ai\Support\FulfillmentContext;
use Laravel\Ai\Prompts\AgentPrompt;

class ResolveAudienceContext
{
    public function __construct(
        protected FulfillmentContext $fulfillmentContext = new FulfillmentContext,
        protected ThreadContextResolver $threadContextResolver = new ThreadContextResolver,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $thread = $this->resolveThread($prompt);
        $actor = $this->resolveActor($prompt);

        if (! $thread || ! $actor) {
            return $next($prompt);
        }

        $channel = $this->threadContextResolver->resolveChannel($thread);
        $requestPost = $this->fulfillmentContext->resolveSubjectFromThread($thread);
        $speakerParty = $this->resolveSpeakerParty($actor, $requestPost);
        $participantCount = $this->resolveParticipantCount($channel, $requestPost);
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

    protected function resolveSpeakerParty(User $actor, ?Post $requestPost): string
    {
        if (! $requestPost) {
            return 'member';
        }

        if ($this->fulfillmentContext->isRequester($requestPost, $actor)) {
            return 'asker';
        }

        if ($this->fulfillmentContext->isTargetProfileParticipant($requestPost, $actor)) {
            return 'worker';
        }

        $order = $this->fulfillmentContext->currentOrder($requestPost);
        $sellerProfile = $order?->sellerProfileRecord();

        if ($sellerProfile && $sellerProfile->user_id === $actor->id) {
            return 'worker';
        }

        if ($this->fulfillmentContext->hasParticipant($requestPost, $actor)) {
            return 'member';
        }

        return 'external';
    }

    protected function resolveParticipantCount(?Channel $channel, ?Post $requestPost): int
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

        if ($requestPost) {
            return 2;
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
