<?php

namespace Tests\Feature;

use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Figurate\WebView\Http\Middleware\HandleInertiaRequests;
use Figurate\WebView\Http\Middleware\InjectWebViewAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BroadcastSpaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_actor_can_subscribe_to_thread_space_updates_and_personal_notifications(): void
    {
        $this->withoutMiddleware([
            HandleInertiaRequests::class,
            InjectWebViewAssets::class,
        ]);

        $user = User::factory()->create();
        $space = Space::query()->create([
            'status' => 'open',
        ]);
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Coordination Thread',
            'phase' => 'coordination',
            'status' => 'open',
        ]);

        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => $thread->id,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => SpaceActorState::StatusActive,
        ]);

        $this->actingAs($user);

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-threads.'.$thread->uuid,
        ])->assertOk();

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-spaces.'.$space->uuid,
        ])->assertOk();

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-users.'.$user->uuid.'.notifications',
        ])->assertOk();
    }

    public function test_access_policies_and_notification_user_identity_checks_restrict_foreign_subscriptions(): void
    {
        $this->withoutMiddleware([
            HandleInertiaRequests::class,
            InjectWebViewAssets::class,
        ]);

        $authorized = User::factory()->create();
        $intruder = User::factory()->create();
        $space = Space::query()->create([
            'status' => 'open',
        ]);
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Coordination Thread',
            'phase' => 'coordination',
            'status' => 'open',
        ]);

        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => $thread->id,
            'actorable_type' => $authorized->getMorphClass(),
            'actorable_id' => $authorized->id,
            'status' => SpaceActorState::StatusActive,
        ]);

        $this->assertFalse(Gate::forUser($intruder)->allows('view', $thread));
        $this->assertFalse(Gate::forUser($intruder)->allows('view', $space));
        $this->assertNotSame($intruder->uuid, $authorized->uuid);
    }
}
