<?php

namespace Tests\Unit;

use App\Models\Server\Channel;
use App\Models\Server\Thread;
use App\Support\Channels\ChannelSkillContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChannelSkillContextResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_attached_and_referenced_channel_route_and_address_skills(): void
    {
        Storage::fake('public');

        $channel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'config' => [
                'skills' => ['provider-send'],
            ],
        ]);
        $route = $channel->routes()->create([
            'config' => [
                'skills' => ['route-general'],
                'inbound' => [
                    'skills' => ['route-inbound'],
                ],
                'outbound' => [
                    'skills' => ['route-outbound'],
                ],
            ],
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'data' => [],
            'meta' => [],
        ]);
        $thread = Thread::factory()->create();
        $address = $route->addresses()->create([
            'addressable_type' => $thread->getMorphClass(),
            'addressable_id' => $thread->getKey(),
            'provider' => 'generic',
            'target' => 'target-123',
            'target_type' => 'external_target',
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'data' => [
                'skills' => ['address-general'],
            ],
            'meta' => [],
        ]);

        foreach (['provider-send', 'route-general', 'route-inbound', 'route-outbound', 'address-general'] as $slug) {
            $channel->addMediaFromString(<<<MARKDOWN
---
name: {$slug}
description: {$slug} description
---

# {$slug}

Use {$slug}.
MARKDOWN)
                ->usingName($slug)
                ->usingFileName("{$slug}.md")
                ->withCustomProperties([
                    'skill_slug' => $slug,
                    'description' => "{$slug} description",
                ])
                ->toMediaCollection(Channel::SkillCollection, 'public');
        }

        $route->addMediaFromString(<<<'MARKDOWN'
---
name: route-attached
description: route attached description
---

# route-attached

Use route-attached.
MARKDOWN)
            ->usingName('route-attached')
            ->usingFileName('route-attached.md')
            ->withCustomProperties([
                'skill_slug' => 'route-attached',
                'description' => 'route attached description',
            ])
            ->toMediaCollection(Channel::SkillCollection, 'public');

        $address->addMediaFromString(<<<'MARKDOWN'
---
name: address-attached
description: address attached description
---

# address-attached

Use address-attached.
MARKDOWN)
            ->usingName('address-attached')
            ->usingFileName('address-attached.md')
            ->withCustomProperties([
                'skill_slug' => 'address-attached',
                'description' => 'address attached description',
            ])
            ->toMediaCollection(Channel::SkillCollection, 'public');

        $resolved = app(ChannelSkillContextResolver::class)->resolve($channel, $route, $address);

        $this->assertSame('provider-send', data_get($resolved, 'channel.referenced.0.slug'));
        $this->assertSame('route-attached', data_get($resolved, 'route.attached.0.slug'));
        $this->assertSame('route-general', data_get($resolved, 'route.referenced.0.slug'));
        $this->assertSame('route-inbound', data_get($resolved, 'route.inbound.0.slug'));
        $this->assertSame('route-outbound', data_get($resolved, 'route.outbound.0.slug'));
        $this->assertSame('address-attached', data_get($resolved, 'address.attached.0.slug'));
        $this->assertSame('address-general', data_get($resolved, 'address.referenced.0.slug'));
        $this->assertCount(7, $resolved['entries']);
    }
}
