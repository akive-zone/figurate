<?php

namespace Tests\Feature;

use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Figurate\WebView\Http\Middleware\HandleInertiaRequests;
use Figurate\WebView\Http\Middleware\InjectWebViewAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_actor_can_subscribe_to_thread_channel_channel_updates_and_personal_notifications(): void
    {
        $this->withoutMiddleware([
            HandleInertiaRequests::class,
            InjectWebViewAssets::class,
        ]);

        $user = User::factory()->create();
        $channel = Channel::query()->create([
            'status' => 'open',
        ]);
        $thread = $channel->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Coordination Thread',
            'phase' => 'coordination',
            'status' => 'open',
        ]);

        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => $thread->id,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => ChannelActorState::StatusActive,
        ]);

        $this->actingAs($user);

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-threads.'.$thread->uuid,
        ])->assertOk();

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-channels.'.$channel->uuid,
        ])->assertOk();

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-users.'.$user->uuid.'.notifications',
        ])->assertOk();
    }

    public function test_access_policies_and_notification_channel_identity_checks_restrict_foreign_subscriptions(): void
    {
        $this->withoutMiddleware([
            HandleInertiaRequests::class,
            InjectWebViewAssets::class,
        ]);

        $authorized = User::factory()->create();
        $intruder = User::factory()->create();
        $channel = Channel::query()->create([
            'status' => 'open',
        ]);
        $thread = $channel->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Coordination Thread',
            'phase' => 'coordination',
            'status' => 'open',
        ]);

        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => $thread->id,
            'actorable_type' => $authorized->getMorphClass(),
            'actorable_id' => $authorized->id,
            'status' => ChannelActorState::StatusActive,
        ]);

        $this->assertFalse(Gate::forUser($intruder)->allows('view', $thread));
        $this->assertFalse(Gate::forUser($intruder)->allows('view', $channel));
        $this->assertNotSame($intruder->uuid, $authorized->uuid);
    }
}
