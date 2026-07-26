<?php

namespace Tests\Feature;

use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpaceStoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_empty_space_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/spaces');

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.active_thread_id', null);

        $space = Space::query()
            ->where('uuid', $response->json('data.id'))
            ->firstOrFail();

        $this->assertCount(0, $space->threads);
        $this->assertDatabaseHas('actor_states', [
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => SpaceActorState::StatusActive,
        ]);

        $this->getJson('/api/spaces')
            ->assertOk()
            ->assertJsonPath('data.0.id', $space->uuid);
    }

    public function test_it_accepts_a_caller_defined_space_status(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/spaces', [
            'status' => 'preparing',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'preparing');

        $this->assertDatabaseHas('spaces', [
            'uuid' => $response->json('data.id'),
            'status' => 'preparing',
        ]);
    }

    public function test_it_validates_space_status(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/spaces', [
            'status' => str_repeat('x', 51),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('spaces', 0);
    }
}
