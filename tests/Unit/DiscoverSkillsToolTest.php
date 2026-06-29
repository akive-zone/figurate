<?php

namespace Tests\Unit;

use App\Ai\Tools\DiscoverSkillsTool;
use App\Models\Server\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\TestCase;

class DiscoverSkillsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_discovers_media_backed_skills(): void
    {
        Storage::fake('public');

        $channel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'server' => 'whatsapp-waha',
        ]);

        $channel->addMediaFromString(<<<'MARKDOWN'
---
name: waha-whatsapp
description: Format and normalize WAHA WhatsApp messages.
---

# WAHA WhatsApp

Use WAHA sendText for outbound WhatsApp messages.
MARKDOWN)
            ->usingName('waha-whatsapp')
            ->usingFileName('SKILL.md')
            ->withCustomProperties([
                'skill_slug' => 'waha-whatsapp',
                'description' => 'Format WAHA WhatsApp messages.',
            ])
            ->toMediaCollection(Channel::SkillCollection, 'public');

        $response = json_decode((string) (new DiscoverSkillsTool)->handle(new ToolRequest([
            'query' => 'waha',
            'limit' => 5,
            'include_content' => true,
        ])), true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($response['ok']);
        $this->assertSame(1, $response['count']);
        $this->assertSame('media', $response['skills'][0]['source']);
        $this->assertSame('waha-whatsapp', $response['skills'][0]['slug']);
        $this->assertStringContainsString('WAHA sendText', $response['skills'][0]['content_excerpt']);
    }
}
