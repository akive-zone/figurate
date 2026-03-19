<?php

namespace Figurate\FulfillmentManager\Database\Seeders;

use Figurate\FulfillmentManager\Models\Payment;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Payment::factory()->count(5)->create();
    }
}
