<?php

namespace Database\Seeders;

use App\Models\Server\Conversation;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Conversation::factory()->count(5)->create();
    }
}
