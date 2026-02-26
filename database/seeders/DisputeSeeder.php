<?php

namespace Database\Seeders;

use App\Models\Server\Fulfillment\Dispute;
use Illuminate\Database\Seeder;

class DisputeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Dispute::factory()->count(3)->create();
    }
}
