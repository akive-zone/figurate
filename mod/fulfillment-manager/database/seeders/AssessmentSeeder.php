<?php

namespace Figurate\FulfillmentManager\Database\Seeders;

use Figurate\FulfillmentManager\Models\Assessment;
use Illuminate\Database\Seeder;

class AssessmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Assessment::factory()->count(3)->create();
    }
}
