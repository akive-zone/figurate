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
        Schema::create('actor_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id');
            $table->foreignId('thread_id')->nullable();
            $table->nullableMorphs('actorable');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['channel_id', 'actorable_type', 'actorable_id'], 'actor_states_channel_actor_unique');
            $table->index(['channel_id', 'thread_id'], 'actor_states_channel_thread_index');
            $table->index(['status'], 'actor_states_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actor_states');
    }
};
