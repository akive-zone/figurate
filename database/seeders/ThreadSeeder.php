<?php

namespace Database\Seeders;

use App\Models\Server\Thread;
use Illuminate\Database\Seeder;

class ThreadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Thread::factory()->count(5)->create();
    }
}
