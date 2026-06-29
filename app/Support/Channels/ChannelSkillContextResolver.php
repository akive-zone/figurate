<?php

namespace App\Support\Channels;

use App\Ai\Support\Skills\SkillRepository;
use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRoute;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChannelSkillContextResolver
{
    public function __construct(
        protected SkillRepository $skillRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(Channel $channel, ?ChannelRoute $route = null, ?ChannelAddress $address = null, bool $includeContent = true): array
    {
        $channelSkills = [
            'attached' => $this->mediaEntries($channel, $includeContent),
            'referenced' => $this->referencedEntries($this->skillSlugs(data_get($channel->config, 'skills')), $includeContent),
        ];

        $routeSkills = [
            'attached' => $route instanceof ChannelRoute ? $this->mediaEntries($route, $includeContent) : [],
            'referenced' => $route instanceof ChannelRoute
                ? $this->referencedEntries($this->skillSlugs(data_get($route->config, 'skills')), $includeContent)
                : [],
            'inbound' => $route instanceof ChannelRoute
                ? $this->referencedEntries($this->skillSlugs(data_get($route->config, 'inbound.skills')), $includeContent)
                : [],
            'outbound' => $route instanceof ChannelRoute
                ? $this->referencedEntries($this->skillSlugs(data_get($route->config, 'outbound.skills')), $includeContent)
                : [],
        ];

        $addressSkills = [
            'attached' => $address instanceof ChannelAddress ? $this->mediaEntries($address, $includeContent) : [],
            'referenced' => $address instanceof ChannelAddress
                ? $this->referencedEntries($this->skillSlugs(data_get($address->data, 'skills')), $includeContent)
                : [],
            'inbound' => $address instanceof ChannelAddress
                ? $this->referencedEntries($this->skillSlugs(data_get($address->data, 'inbound.skills')), $includeContent)
                : [],
            'outbound' => $address instanceof ChannelAddress
                ? $this->referencedEntries($this->skillSlugs(data_get($address->data, 'outbound.skills')), $includeContent)
                : [],
        ];

        return [
            'channel' => $channelSkills,
            'route' => $routeSkills,
            'address' => $addressSkills,
            'entries' => $this->uniqueEntries([
                ...$channelSkills['attached'],
                ...$channelSkills['referenced'],
                ...$routeSkills['attached'],
                ...$routeSkills['referenced'],
                ...$routeSkills['inbound'],
                ...$routeSkills['outbound'],
                ...$addressSkills['attached'],
                ...$addressSkills['referenced'],
                ...$addressSkills['inbound'],
                ...$addressSkills['outbound'],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Channel $channel, ?ChannelRoute $route = null, ?ChannelAddress $address = null): array
    {
        $resolved = $this->resolve($channel, $route, $address, includeContent: false);

        return [
            'entries' => $resolved['entries'],
            'counts' => [
                'channel' => count($resolved['channel']['attached']) + count($resolved['channel']['referenced']),
                'route' => count($resolved['route']['attached']) + count($resolved['route']['referenced']) + count($resolved['route']['inbound']) + count($resolved['route']['outbound']),
                'address' => count($resolved['address']['attached']) + count($resolved['address']['referenced']) + count($resolved['address']['inbound']) + count($resolved['address']['outbound']),
                'total' => count($resolved['entries']),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mediaEntries(HasMedia $model, bool $includeContent): array
    {
        return collect($model->getMedia(Channel::SkillCollection))
            ->map(fn (Media $media): ?array => $this->skillRepository->fromMedia($media, $includeContent))
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $slugs
     * @return list<array<string, mixed>>
     */
    protected function referencedEntries(array $slugs, bool $includeContent): array
    {
        return $this->skillRepository->resolveMany($slugs, $includeContent);
    }

    /**
     * @return list<string>
     */
    protected function skillSlugs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $slug): bool => is_string($slug) && trim($slug) !== '')
            ->map(fn (string $slug): string => trim($slug))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    protected function uniqueEntries(array $entries): array
    {
        return collect($entries)
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->unique(fn (array $entry): string => implode(':', [
                (string) ($entry['source'] ?? 'unknown'),
                (string) ($entry['slug'] ?? 'unknown'),
                (string) ($entry['skill_path'] ?? 'unknown'),
            ]))
            ->values()
            ->all();
    }
}
