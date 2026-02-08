<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->morphs('actor');
            $table->string('action');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['request_id', 'actor_type', 'actor_id', 'action'], 'request_actors_unique');
            $table->index(['actor_type', 'actor_id', 'action', 'status'], 'request_actors_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_actors');
    }
};
