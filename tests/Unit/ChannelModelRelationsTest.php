<?php

namespace Tests\Unit;

use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRoute;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Tests\TestCase;

class ChannelModelRelationsTest extends TestCase
{
    public function test_channel_links_are_posts_and_graph_edges(): void
    {
        $this->assertSame('channel.link', Post::TypeChannelLink);
        $this->assertSame('channel', Post::RelationRoleChannel);
        $this->assertSame('channel.link', Post::RelationRoleChannelLink);
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

    public function test_addressable_models_expose_channel_addresses_relationships(): void
    {
        $this->assertInstanceOf(MorphMany::class, (new User)->channelAddresses());
        $this->assertInstanceOf(MorphMany::class, (new Space)->channelAddresses());
        $this->assertInstanceOf(MorphMany::class, (new Thread)->channelAddresses());
    }
}
