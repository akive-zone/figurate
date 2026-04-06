<?php

namespace Database\Factories;

use App\Models\Server\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    /**
     * @var class-string<Channel>
     */
    protected $model = Channel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Channel',
            'driver' => Channel::ProtocolGeneric,
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'endpoint_url' => fake()->url(),
            'auth_type' => 'bearer',
            'credentials' => [
                'token' => fake()->sha256(),
            ],
            'config' => [
                'timeout' => 10,
            ],
            'meta' => [
                'source' => 'factory',
            ],
        ];
    }
}
