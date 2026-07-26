<?php

namespace Database\Factories\Server;

use App\Models\Server\ApiIdempotencyRecord;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiIdempotencyRecord>
 */
class ApiIdempotencyRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scope' => 'POST:'.fake()->slug(),
            'idempotency_key' => fake()->uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'status_code' => 201,
            'response_body' => '{"data":{}}',
            'response_headers' => ['Content-Type' => 'application/json'],
        ];
    }
}
