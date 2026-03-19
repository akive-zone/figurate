<?php

namespace Figurate\FulfillmentManager\Database\Seeders;

use Figurate\FulfillmentManager\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Order::factory()->count(3)->create();
    }
}
