<?php

namespace Database\Seeders;

use App\Models\Server\Inbox;
use Illuminate\Database\Seeder;

class InboxSeeder extends Seeder
{
    public function run(): void
    {
        Inbox::factory()->count(10)->create();
    }
}
