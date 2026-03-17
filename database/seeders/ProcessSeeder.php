<?php

namespace Database\Seeders;

use Figurate\FulfillmentManager\Models\Process;
use Illuminate\Database\Seeder;

class ProcessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Process::factory()->count(5)->create();
    }
}
