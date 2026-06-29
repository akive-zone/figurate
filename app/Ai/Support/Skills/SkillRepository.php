<?php

namespace App\Ai\Support\Skills;

use App\Models\Server\Channel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SkillRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function discover(string $query = '', int $limit = 10, bool $includeContent = false): array
    {
        $normalizedQuery = mb_strtolower(trim($query));
        $limit = max(1, min(25, $limit));
        $entries = [];

        foreach ($this->filesystemSkills($includeContent) as $entry) {
            if ($this->matchesQuery($entry, $normalizedQuery)) {
                $entries[] = $entry;
            }
        }

        foreach ($this->mediaSkills($includeContent || $normalizedQuery !== '') as $entry) {
            if ($this->matchesQuery($entry, $normalizedQuery)) {
                if (! $includeContent) {
                    unset($entry['content_excerpt']);
                }

                $entries[] = $entry;
            }
        }

        return array_slice($entries, 0, $limit);
    }

    /**
     * @param  list<string>  $slugs
     * @return list<array<string, mixed>>
     */
    public function resolveMany(array $slugs, bool $includeContent = false): array
    {
        $normalizedSlugs = collect($slugs)
            ->filter(fn (mixed $slug): bool => is_string($slug) && trim($slug) !== '')
            ->map(fn (string $slug): string => mb_strtolower(trim($slug)))
            ->unique()
            ->values();

        if ($normalizedSlugs->isEmpty()) {
            return [];
        }

        return collect($this->discover('', 250, $includeContent))
            ->filter(function (mixed $entry) use ($normalizedSlugs): bool {
                if (! is_array($entry)) {
                    return false;
                }

                $slug = $this->stringValue($entry['slug'] ?? null);

                return $slug !== null && $normalizedSlugs->contains(mb_strtolower($slug));
            })
            ->unique(fn (array $entry): string => implode(':', [
                (string) ($entry['source'] ?? 'unknown'),
                (string) ($entry['slug'] ?? 'unknown'),
                (string) ($entry['skill_path'] ?? 'unknown'),
            ]))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromMedia(Media $media, bool $includeContent = false): ?array
    {
        if ($media->collection_name !== Channel::SkillCollection) {
            return null;
        }

        $raw = $this->mediaContent($media);
        $frontmatter = $raw !== null ? $this->parseFrontmatter($raw) : [];
        $slug = $this->stringValue($media->getCustomProperty('skill_slug'))
            ?? $this->stringValue($media->getCustomProperty('slug'))
            ?? pathinfo($media->file_name, PATHINFO_FILENAME);
        $entry = [
            'source' => 'media',
            'slug' => $slug,
            'name' => $this->stringValue($media->getCustomProperty('name'))
                ?? (string) ($frontmatter['name'] ?? $media->name ?: $slug),
            'description' => $this->stringValue($media->getCustomProperty('description'))
                ?? (string) ($frontmatter['description'] ?? ''),
            'skill_path' => "media:{$media->uuid}",
            'media_id' => $media->id,
            'media_uuid' => $media->uuid,
            'media_model' => $media->model_type,
            'media_model_id' => $media->model_id,
            'disk' => $media->disk,
            'references' => [],
        ];

        if ($includeContent && $raw !== null) {
            $entry['content_excerpt'] = mb_substr(trim($raw), 0, 1500);
        }

        return $entry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function filesystemSkills(bool $includeContent): array
    {
        $entries = [];

        foreach ($this->skillRoots() as $skillsRoot) {
            foreach (File::directories($skillsRoot) as $dir) {
                $skillFile = $dir.'/SKILL.md';
                if (! File::exists($skillFile)) {
                    continue;
                }

                $raw = (string) File::get($skillFile);
                $frontmatter = $this->parseFrontmatter($raw);
                $slug = basename($dir);
                $entry = [
                    'source' => 'filesystem',
                    'slug' => $slug,
                    'name' => (string) ($frontmatter['name'] ?? $slug),
                    'description' => (string) ($frontmatter['description'] ?? ''),
                    'skill_path' => str_replace(base_path().'/', '', $skillFile),
                    'references' => $this->referenceFiles($dir.'/references'),
                ];

                if ($includeContent) {
                    $entry['content_excerpt'] = mb_substr(trim($raw), 0, 1500);
                }

                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mediaSkills(bool $includeContent): array
    {
        return Media::query()
            ->where('collection_name', Channel::SkillCollection)
            ->latest('id')
            ->get()
            ->map(fn (Media $media): ?array => $this->fromMedia($media, $includeContent))
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->values()
            ->all();
    }

    protected function mediaContent(Media $media): ?string
    {
        try {
            $contents = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

            return is_string($contents) ? $contents : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function matchesQuery(array $entry, string $query): bool
    {
        if ($query === '') {
            return true;
        }

        $searchText = mb_strtolower(implode(' ', [
            $entry['slug'] ?? '',
            $entry['name'] ?? '',
            $entry['description'] ?? '',
            $entry['skill_path'] ?? '',
            $entry['content_excerpt'] ?? '',
            implode(' ', is_array($entry['references'] ?? null) ? $entry['references'] : []),
        ]));

        return str_contains($searchText, $query);
    }

    /**
     * @return array<string, string>
     */
    protected function parseFrontmatter(string $markdown): array
    {
        if (! preg_match('/^---\\s*\\n(.*?)\\n---\\s*\\n/s', $markdown, $matches)) {
            return [];
        }

        $result = [];
        foreach (preg_split('/\\r?\\n/', trim($matches[1])) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $result[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    protected function referenceFiles(string $referencesPath): array
    {
        if (! File::isDirectory($referencesPath)) {
            return [];
        }

        $files = array_map(
            static fn (\SplFileInfo $file): string => str_replace(base_path().'/', '', $file->getPathname()),
            File::files($referencesPath),
        );

        sort($files);

        return array_values($files);
    }

    /**
     * @return list<string>
     */
    protected function skillRoots(): array
    {
        $roots = [
            resource_path('figurate/skills'),
            resource_path('skills'),
            ...File::glob(base_path('mod/*/resources/figurate/skills')),
            ...File::glob(base_path('ext/*/resources/figurate/skills')),
            ...File::glob(base_path('vendor/*/*/resources/figurate/skills')),
            ...File::glob(base_path('node_modules/*/resources/figurate/skills')),
            ...File::glob(base_path('node_modules/@*/*/resources/figurate/skills')),
        ];

        return array_values(array_filter(
            array_unique($roots),
            static fn (string $path): bool => File::isDirectory($path),
        ));
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
