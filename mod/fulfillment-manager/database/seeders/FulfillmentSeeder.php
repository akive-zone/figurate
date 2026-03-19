<?php

namespace Figurate\FulfillmentManager\Database\Seeders;

use Illuminate\Database\Seeder;

class FulfillmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            ServiceCategorySeeder::class,
            RequestSeeder::class,
            AssessmentSeeder::class,
            QuoteSeeder::class,
            OrderSeeder::class,
            PaymentSeeder::class,
            RatingSeeder::class,
            DisputeSeeder::class,
            ProcessSeeder::class,
        ]);
    }
}
