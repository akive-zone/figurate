<?php

namespace App\Ai\Support\Observer;

use App\Models\Server\ThreadActor;
use Illuminate\Support\Facades\File;

class ObserverSkillRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function resolve(ThreadActor $threadActor): ?array
    {
        $slug = $this->resolveSkillSlug($threadActor);

        if ($slug === null) {
            return null;
        }

        return $this->loadDefinition($slug);
    }

    protected function resolveSkillSlug(ThreadActor $threadActor): ?string
    {
        $configuredSlug = is_array($threadActor->config)
            ? ($threadActor->config['observer_skill'] ?? null)
            : null;

        if (is_string($configuredSlug) && trim($configuredSlug) !== '') {
            return trim($configuredSlug);
        }

        return match ($threadActor->actorName()) {
            ThreadActor::ActorSafetyGuard => 'safety-guard',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function loadDefinition(string $slug): ?array
    {
        $path = resource_path("skills/observers/{$slug}/skill.json");

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        $decoded['slug'] = $slug;

        return $decoded;
    }
}
