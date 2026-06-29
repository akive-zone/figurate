<?php

namespace Tests\Feature;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChannelStoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bootstraps_a_space_for_user_owned_channels_without_an_assigned_space(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $response = $this->postJson(route('api.channels.store'), [
            'owner_type' => 'user',
            'owner_id' => 'me',
            'protocol' => Channel::ProtocolGeneric,
            'name' => 'whatsapp-waha',
            'label' => 'WhatsApp WAHA',
            'transport' => Channel::TransportWebhook,
            'endpoint_url' => 'https://hooks.example/inbound',
        ]);

        $response->assertCreated()
            ->assertJsonPath('owner.type', 'user')
            ->assertJsonPath('data.protocol', Channel::ProtocolGeneric)
            ->assertJsonPath('data.space.id', fn (mixed $value): bool => is_string($value) && $value !== '')
            ->assertJsonPath('space.id', fn (mixed $value): bool => is_string($value) && $value !== '');

        $channel = Channel::query()->where('server', 'whatsapp-waha')->firstOrFail();
        $space = Space::query()->where('uuid', (string) $response->json('space.id'))->firstOrFail();

        $this->assertTrue($channel->relations()
            ->where('relationable_type', $user->getMorphClass())
            ->where('relationable_id', $user->getKey())
            ->exists());
        $this->assertTrue($channel->relations()
            ->where('relationable_type', $space->getMorphClass())
            ->where('relationable_id', $space->getKey())
            ->where('kind', ChannelRelation::KindLink)
            ->exists());
        $this->assertTrue(SpaceActorState::query()
            ->where('space_id', $space->id)
            ->where('actorable_type', $user->getMorphClass())
            ->where('actorable_id', $user->id)
            ->exists());
        $this->assertTrue($space->threads()->exists());
    }

    public function test_it_uses_the_assigned_space_when_space_id_is_provided(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();
        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => SpaceActorState::StatusActive,
        ]);

        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $response = $this->postJson(route('api.channels.store'), [
            'owner_type' => 'user',
            'owner_id' => 'me',
            'space_id' => $space->uuid,
            'protocol' => Channel::ProtocolGeneric,
            'name' => 'vendor-webhook',
            'label' => 'Vendor Webhook',
            'transport' => Channel::TransportWebhook,
            'endpoint_url' => 'https://hooks.example/vendor',
        ]);

        $response->assertCreated()
            ->assertJsonPath('space.id', $space->uuid)
            ->assertJsonPath('data.space.id', $space->uuid);

        $channel = Channel::query()->where('server', 'vendor-webhook')->firstOrFail();

        $this->assertTrue($channel->relations()
            ->where('relationable_type', $space->getMorphClass())
            ->where('relationable_id', $space->getKey())
            ->exists());
    }
}
