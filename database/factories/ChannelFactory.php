<?php

namespace Database\Factories;

use App\Models\Server\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server\Channel>
 */
class ChannelFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Server\Channel>
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
            'status' => 'open',
        ];
    }
}
