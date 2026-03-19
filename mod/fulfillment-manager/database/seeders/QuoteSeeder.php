<?php

namespace Figurate\FulfillmentManager\Database\Seeders;

use Figurate\FulfillmentManager\Models\Quote;
use Illuminate\Database\Seeder;

class QuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Quote::factory()->count(5)->create();
    }
}
