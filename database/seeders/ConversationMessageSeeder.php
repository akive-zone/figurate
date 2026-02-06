<?php

namespace Database\Seeders;

use App\Models\Server\ConversationMessage;
use Illuminate\Database\Seeder;

class ConversationMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ConversationMessage::factory()->count(20)->create();
    }
}
