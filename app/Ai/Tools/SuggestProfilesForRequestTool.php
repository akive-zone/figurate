<?php

namespace App\Ai\Tools;

use App\Ai\Support\FulfillmentContext;
use App\Ai\Support\ThreadContextResolver;
use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Profile;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class SuggestProfilesForRequestTool implements Tool
{
    public function __construct(
        protected Thread $thread,
        protected User $actor,
        protected FulfillmentContext $fulfillmentContext = new FulfillmentContext,
        protected ThreadContextResolver $threadContextResolver = new ThreadContextResolver,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Suggest matching worker profiles for the current request using request context and profile categories.';
    }

    /**
     * Execute the tool.
     */
    public function handle(ToolRequest $request): Stringable|string
    {
        $channel = $this->threadContextResolver->resolveChannel($this->thread);
        $requestPost = $this->fulfillmentContext->resolveSubjectFromThread($this->thread);

        if (! $requestPost) {
            return $this->encodeError('No request exists for this channel yet.');
        }

        if ($channel && ! $this->channelAllowsActor($channel, $requestPost)) {
            return $this->encodeError('Actor does not have access to request profile suggestions.');
        }

        $limit = (int) ($request['limit'] ?? 3);
        $limit = max(1, min(10, $limit));

        $query = trim((string) ($request['query'] ?? ''));
        if ($query === '') {
            $query = trim(implode(' ', array_filter([
                (string) $this->fulfillmentContext->title($requestPost),
                (string) $this->fulfillmentContext->description($requestPost),
                (string) $this->fulfillmentContext->flowType($requestPost),
            ])));
        }

        $keywords = $this->keywords($query);

        $profiles = Profile::query()
            ->with('categories')
            ->where(function ($profileQuery): void {
                $profileQuery
                    ->where('status', 'approved')
                    ->orWhereNull('status');
            })
            ->limit(200)
            ->get()
            ->map(function (Profile $profile) use ($keywords): array {
                $categories = $profile->categories
                    ->map(fn ($category) => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ])
                    ->values()
                    ->all();

                $score = $this->scoreProfile($profile, $keywords);

                return [
                    'profile' => $profile,
                    'score' => $score,
                    'categories' => $categories,
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] > 0 || $keywords === [])
            ->sortByDesc('score')
            ->values()
            ->take($limit)
            ->map(function (array $candidate): array {
                /** @var Profile $profile */
                $profile = $candidate['profile'];

                return [
                    'id' => $profile->id,
                    'uuid' => $profile->uuid,
                    'display_name' => $profile->display_name,
                    'location' => $profile->location,
                    'status' => $profile->status,
                    'score' => $candidate['score'],
                    'categories' => $candidate['categories'],
                    'reason' => $this->reasonForMatch($profile, $candidate['categories']),
                ];
            })
            ->all();

        return json_encode([
            'ok' => true,
            'request' => [
                'id' => $requestPost->id,
                'ulid' => $requestPost->ulid,
                'title' => $this->fulfillmentContext->title($requestPost),
                'status' => $requestPost->status,
            ],
            'query' => $query,
            'suggestions' => $profiles,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }

    protected function channelAllowsActor(Channel $channel, Post $requestPost): bool
    {
        return $this->fulfillmentContext->hasParticipant($requestPost, $this->actor)
            || $channel->hasActor($this->actor);
    }

    /**
     * @return list<string>
     */
    protected function keywords(string $query): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', mb_strtolower($query)) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 3));

        return array_values(array_unique($tokens));
    }

    /**
     * @param  list<string>  $keywords
     */
    protected function scoreProfile(Profile $profile, array $keywords): int
    {
        if ($keywords === []) {
            return 1;
        }

        $categoryText = $profile->categories
            ->flatMap(fn ($category): array => [(string) $category->name, (string) $category->slug])
            ->implode(' ');

        $haystack = mb_strtolower(trim(implode(' ', array_filter([
            (string) $profile->display_name,
            (string) $profile->bio,
            (string) $profile->location,
            $categoryText,
        ]))));

        $score = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * @param  list<array{id:int,name:?string,slug:?string}>  $categories
     */
    protected function reasonForMatch(Profile $profile, array $categories): string
    {
        $categoryNames = array_values(array_filter(array_map(
            fn (array $category): ?string => $category['name'] ?: $category['slug'],
            $categories,
        )));

        if ($categoryNames !== []) {
            return 'Category match: '.implode(', ', array_slice($categoryNames, 0, 3));
        }

        if (is_string($profile->bio) && trim($profile->bio) !== '') {
            return 'Profile bio appears relevant to request scope.';
        }

        return 'General profile match for this request.';
    }

    protected function encodeError(string $message): string
    {
        return json_encode([
            'ok' => false,
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES);
    }
}
