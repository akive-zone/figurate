<?php

namespace Database\Factories\Server;

use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRoute;
use App\Models\Server\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelAddress>
 */
class ChannelAddressFactory extends Factory
{
    /**
     * @var class-string<ChannelAddress>
     */
    protected $model = ChannelAddress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_route_id' => ChannelRoute::factory(),
            'addressable_type' => (new Thread)->getMorphClass(),
            'addressable_id' => Thread::factory(),
            'label' => fake()->words(2, true),
            'provider' => 'waha',
            'target' => fake()->numerify('###########').'@c.us',
            'target_type' => 'whatsapp_chat',
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'data' => [],
            'meta' => [
                'source' => 'factory',
            ],
        ];
    }
}
