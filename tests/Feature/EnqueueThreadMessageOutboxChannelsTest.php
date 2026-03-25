<?php

namespace Tests\Feature;

use App\Features\Actions\Conversation\EnqueueThreadMessageOutbox;
use App\Features\Actions\Conversation\Protocols\ChannelProtocol;
use App\Jobs\DeliverOutboxMessage;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Outbox;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnqueueThreadMessageOutboxChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_enqueues_channel_outbox_records_for_active_thread_channel_bindings(): void
    {
        Queue::fake();

        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Channel Outbox Thread',
            'phase' => 'execution',
            'status' => 'open',
        ]);
        $channel = Channel::factory()->create([
            'driver' => Channel::DriverGeneric,
        ]);

        $thread->channels()->attach($channel->id, [
            'kind' => ChannelRelation::KindBind,
            'status' => 'active',
            'direction' => Channel::DirectionOutbound,
            'data' => json_encode([
                'provider_identifier' => 'gw-thread-44',
                'config' => ['route' => 'primary'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $message = $thread->messages()->create([
            'type' => 'text',
            'text' => 'External delivery payload',
            'senderable_type' => $sender->getMorphClass(),
            'senderable_id' => $sender->id,
            'meta' => [
                'source' => 'peer_message',
            ],
        ]);

        $created = app(EnqueueThreadMessageOutbox::class)->execute($message);

        $this->assertCount(1, $created);
        $outbox = $created->first();
        $this->assertInstanceOf(Outbox::class, $outbox);
        $this->assertSame(ChannelProtocol::Key, $outbox->protocol);
        $this->assertSame(Channel::DriverGeneric, $outbox->provider);
        $this->assertSame('gw-thread-44', $outbox->target);
        $this->assertSame($channel->uuid, data_get($outbox->payload, 'delivery.channel.uuid'));
        $this->assertSame('gw-thread-44', data_get($outbox->payload, 'delivery.binding.provider_identifier'));
        $this->assertSame('primary', data_get($outbox->payload, 'delivery.binding.config.route'));

        Queue::assertPushed(DeliverOutboxMessage::class, function (DeliverOutboxMessage $job) use ($outbox): bool {
            return $job->outboxId === $outbox->id;
        });
    }
}
