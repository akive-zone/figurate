<?php

namespace Database\Factories;

use App\Models\Server\Post;
use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Request;
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
            'postable_type' => Request::class,
            'postable_id' => Request::factory(),
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
