<?php

namespace Database\Seeders;

use App\Models\Server\Message;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Message::factory()->count(20)->create();
    }
}
