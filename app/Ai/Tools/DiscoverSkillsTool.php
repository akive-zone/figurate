<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class DiscoverSkillsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Discover agent skills and retrieve concise guidance snippets.';
    }

    public function handle(ToolRequest $request): Stringable|string
    {
        $query = mb_strtolower(trim((string) ($request['query'] ?? '')));
        $limit = max(1, min(25, (int) ($request['limit'] ?? 10)));
        $includeContent = (bool) ($request['include_content'] ?? false);

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
                $name = (string) ($frontmatter['name'] ?? $slug);
                $description = (string) ($frontmatter['description'] ?? '');
                $references = $this->referenceFiles($dir.'/references');

                $searchText = mb_strtolower(implode(' ', [
                    $slug,
                    $name,
                    $description,
                    $raw,
                    implode(' ', $references),
                ]));

                if ($query !== '' && ! str_contains($searchText, $query)) {
                    continue;
                }

                $entry = [
                    'slug' => $slug,
                    'name' => $name,
                    'description' => $description,
                    'skill_path' => str_replace(base_path().'/', '', $skillFile),
                    'references' => $references,
                ];

                if ($includeContent) {
                    $entry['content_excerpt'] = mb_substr(trim($raw), 0, 1500);
                }

                $entries[] = $entry;
            }
        }

        $entries = array_slice($entries, 0, $limit);

        return json_encode([
            'ok' => true,
            'count' => count($entries),
            'skills' => $entries,
        ], JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string(),
            'limit' => $schema->integer(),
            'include_content' => $schema->boolean(),
        ];
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
            resource_path('skills'),
            ...File::glob(base_path('mod/*/resources/skills')),
        ];

        return array_values(array_filter(
            array_unique($roots),
            static fn (string $path): bool => File::isDirectory($path),
        ));
    }
}
