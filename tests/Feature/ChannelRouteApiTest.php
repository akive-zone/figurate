<?php

namespace Tests\Feature;

use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRelation;
use App\Models\Server\ChannelRoute;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChannelRouteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_bidirectional_route_and_thread_address_for_a_channel(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'WhatsApp Support',
            'phase' => 'open',
            'status' => 'open',
        ]);
        $channel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'server' => 'whatsapp-waha',
            'label' => 'WhatsApp WAHA',
        ]);

        $channel->relations()->create([
            'relationable_type' => $user->getMorphClass(),
            'relationable_id' => $user->id,
            'kind' => ChannelRelation::KindLink,
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'config' => [],
            'data' => [],
            'meta' => [],
        ]);
        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => SpaceActorState::StatusActive,
        ]);

        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $routeResponse = $this->postJson(route('api.channels.routes.store', ['channel' => $channel->id]), [
            'name' => 'default-session',
            'label' => 'Default WAHA Session',
            'direction' => Channel::DirectionBidirectional,
            'config' => [
                'provider' => 'waha',
                'inbound' => [
                    'transport' => Channel::TransportWebhook,
                    'path' => '/api/channel-routes/inbound/waha',
                ],
                'outbound' => [
                    'transport' => Channel::TransportHttp,
                    'endpoint_url' => 'https://waha.example/api/sendText',
                ],
            ],
            'data' => [
                'session' => 'default',
            ],
        ]);

        $routeResponse->assertCreated()
            ->assertJsonPath('data.protocol', Channel::ProtocolGeneric)
            ->assertJsonPath('data.name', 'default-session')
            ->assertJsonPath('data.config.inbound.transport', Channel::TransportWebhook)
            ->assertJsonPath('data.config.outbound.transport', Channel::TransportHttp)
            ->assertJsonPath('data.inbound.transport', Channel::TransportWebhook)
            ->assertJsonPath('data.inbound.enabled', true)
            ->assertJsonPath('data.inbound.url', fn (mixed $value): bool => is_string($value) && str_contains($value, '/channel-routes/'))
            ->assertJsonPath('data.outbound.transport', Channel::TransportHttp);

        $route = ChannelRoute::query()->where('channel_id', $channel->id)->first();

        $this->assertInstanceOf(ChannelRoute::class, $route);

        $addressResponse = $this->postJson(route('api.channels.routes.addresses.store', [
            'channel' => $channel->id,
            'route' => $route->id,
        ]), [
            'addressable_type' => 'thread',
            'addressable_id' => $thread->uuid,
            'label' => 'Customer WhatsApp Chat',
            'provider' => 'waha',
            'target' => '2348012345678@c.us',
            'target_type' => 'whatsapp_chat',
            'direction' => Channel::DirectionBidirectional,
            'data' => [
                'phone' => '+2348012345678',
            ],
        ]);

        $addressResponse->assertCreated()
            ->assertJsonPath('data.route.id', $route->id)
            ->assertJsonPath('data.addressable.type', 'thread')
            ->assertJsonPath('data.addressable.id', $thread->uuid)
            ->assertJsonPath('data.provider', 'waha')
            ->assertJsonPath('data.target', '2348012345678@c.us');

        $address = ChannelAddress::query()->where('channel_route_id', $route->id)->first();

        $this->assertInstanceOf(ChannelAddress::class, $address);
        $this->assertTrue($thread->channelAddresses()->whereKey($address->id)->exists());
    }

    public function test_it_adds_media_backed_skills_to_channel_routes(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $channel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'server' => 'whatsapp-waha',
        ]);
        $channel->relations()->create([
            'relationable_type' => $user->getMorphClass(),
            'relationable_id' => $user->id,
            'kind' => ChannelRelation::KindLink,
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'config' => [],
            'data' => [],
            'meta' => [],
        ]);
        $route = $channel->routes()->create([
            'name' => 'default-session',
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'config' => [],
            'data' => [],
            'meta' => [],
        ]);

        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $response = $this->postJson(route('api.channels.routes.skills.store', [
            'channel' => $channel->id,
            'route' => $route->id,
        ]), [
            'content' => "# WAHA WhatsApp\n\nUse sendText for outbound messages.",
            'filename' => 'waha-whatsapp.md',
            'disk' => 'public',
            'skill_slug' => 'waha-whatsapp',
            'description' => 'WAHA route send and receive behavior.',
            'capabilities' => ['outbound_formatting'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.collection', Channel::SkillCollection)
            ->assertJsonPath('data.skill_slug', 'waha-whatsapp')
            ->assertJsonPath('data.description', 'WAHA route send and receive behavior.');

        $this->assertTrue($route->getMedia(Channel::SkillCollection)->contains('file_name', 'waha-whatsapp.md'));
    }
}
