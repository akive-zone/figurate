<?php

namespace Database\Seeders;

use App\Models\Server\Space;
use Illuminate\Database\Seeder;

class SpaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Space::factory()->count(5)->create();
    }
}
