<?php

namespace Database\Seeders;

use App\Models\WorkLog;
use Illuminate\Database\Seeder;

class WorkLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WorkLog::factory()->count(5)->create();
    }
}
