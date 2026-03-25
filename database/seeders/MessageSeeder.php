<?php

namespace Database\Seeders;

use App\Models\Server\Post;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Post::factory()->count(20)->create();
    }
}
