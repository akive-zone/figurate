<?php

namespace Tests\Unit;

use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRoute;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Tests\TestCase;

class ChannelModelRelationsTest extends TestCase
{
    public function test_channel_exposes_threads_relationship(): void
    {
        $relationship = (new Channel)->threads();

        $this->assertInstanceOf(MorphToMany::class, $relationship);
        $this->assertSame('channel_relations', $relationship->getTable());
        $this->assertInstanceOf(Thread::class, $relationship->getRelated());
    }

    public function test_channel_exposes_connections_relationship(): void
    {
        $relationship = (new Channel)->connections();

        $this->assertInstanceOf(HasMany::class, $relationship);
        $this->assertSame('channel_relations', $relationship->getRelated()->getTable());
    }

    public function test_channel_exposes_routes_relationship(): void
    {
        $relationship = (new Channel)->routes();

        $this->assertInstanceOf(HasMany::class, $relationship);
        $this->assertInstanceOf(ChannelRoute::class, $relationship->getRelated());
    }

    public function test_channel_route_exposes_addresses_relationship(): void
    {
        $relationship = (new ChannelRoute)->addresses();

        $this->assertInstanceOf(HasMany::class, $relationship);
        $this->assertInstanceOf(ChannelAddress::class, $relationship->getRelated());
    }

    public function test_thread_exposes_channels_relationship(): void
    {
        $relationship = (new Thread)->channels();

        $this->assertInstanceOf(MorphToMany::class, $relationship);
        $this->assertSame('channel_relations', $relationship->getTable());
        $this->assertInstanceOf(Channel::class, $relationship->getRelated());
    }

    public function test_link_owners_expose_linked_channels_relationships(): void
    {
        $this->assertInstanceOf(MorphToMany::class, (new User)->linkedChannels());
        $this->assertInstanceOf(MorphToMany::class, (new Space)->linkedChannels());
        $this->assertInstanceOf(MorphToMany::class, (new Thread)->linkedChannels());
    }

    public function test_addressable_models_expose_channel_addresses_relationships(): void
    {
        $this->assertInstanceOf(MorphMany::class, (new User)->channelAddresses());
        $this->assertInstanceOf(MorphMany::class, (new Space)->channelAddresses());
        $this->assertInstanceOf(MorphMany::class, (new Thread)->channelAddresses());
    }
}
