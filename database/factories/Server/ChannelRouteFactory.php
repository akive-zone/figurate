<?php

namespace Database\Factories\Server;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelRoute>
 */
class ChannelRouteFactory extends Factory
{
    /**
     * @var class-string<ChannelRoute>
     */
    protected $model = ChannelRoute::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'name' => fake()->slug(2),
            'label' => fake()->words(2, true),
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'config' => [
                'transport' => Channel::TransportHttp,
            ],
            'data' => [],
            'meta' => [
                'source' => 'factory',
            ],
        ];
    }
}
