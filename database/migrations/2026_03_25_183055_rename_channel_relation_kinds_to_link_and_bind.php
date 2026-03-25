<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('channel_relations')
            ->where('kind', 'owner')
            ->update(['kind' => 'link']);

        DB::table('channel_relations')
            ->where('kind', 'binding')
            ->update(['kind' => 'bind']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('channel_relations')
            ->where('kind', 'link')
            ->update(['kind' => 'owner']);

        DB::table('channel_relations')
            ->where('kind', 'bind')
            ->update(['kind' => 'binding']);
    }
};
