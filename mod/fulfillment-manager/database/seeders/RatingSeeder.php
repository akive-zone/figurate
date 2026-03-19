<?php

namespace Figurate\FulfillmentManager\Database\Seeders;

use Figurate\FulfillmentManager\Models\Rating;
use Illuminate\Database\Seeder;

class RatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Rating::factory()->count(5)->create();
    }
}
