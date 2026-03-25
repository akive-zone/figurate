<?php

namespace Tests\Unit;

use App\Models\Server\Channel;
use App\Models\Server\Thread;
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

    public function test_thread_exposes_channels_relationship(): void
    {
        $relationship = (new Thread)->channels();

        $this->assertInstanceOf(MorphToMany::class, $relationship);
        $this->assertSame('channel_relations', $relationship->getTable());
        $this->assertInstanceOf(Channel::class, $relationship->getRelated());
    }
}
