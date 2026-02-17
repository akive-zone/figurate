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
        Schema::create('channel_actor_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained('threads')->nullOnDelete();
            $table->morphs('actor');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['channel_id', 'actor_type', 'actor_id'], 'channel_actor_states_channel_actor_unique');
            $table->index(['channel_id', 'thread_id'], 'channel_actor_states_channel_thread_index');
            $table->index(['status'], 'channel_actor_states_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_actor_states');
    }
};
