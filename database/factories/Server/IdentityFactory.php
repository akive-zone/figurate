<?php

namespace Database\Factories\Server;

use App\Models\Server\Identity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Identity>
 */
class IdentityFactory extends Factory
{
    /**
     * @var class-string<Identity>
     */
    protected $model = Identity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['oidc', 'oauth2']),
            'provider_subject' => fake()->unique()->uuid(),
            'payload' => [
                'email' => fake()->safeEmail(),
                'username' => fake()->userName(),
                'tokens' => [
                    'access' => Str::random(64),
                    'refresh' => Str::random(64),
                ],
                'claims' => [
                    'name' => fake()->name(),
                ],
            ],
            'linked_at' => now(),
            'last_used_at' => now(),
        ];
    }
}
