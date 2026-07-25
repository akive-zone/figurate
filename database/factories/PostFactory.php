<?php

namespace Database\Factories;

use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @var class-string<Post>
     */
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'postable_type' => Space::class,
            'postable_id' => Space::factory(),
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => fake()->paragraph(),
            ],
            'meta' => null,
            'occurred_at' => now(),
        ];
    }

    public function fromSender(User $user): static
    {
        return $this->afterCreating(function (Post $post) use ($user): void {
            $post->attachRelation($user, Post::RelationRoleSender);
        });
    }
}
