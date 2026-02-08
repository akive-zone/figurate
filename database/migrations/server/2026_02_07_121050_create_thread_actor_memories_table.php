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
        Schema::create('thread_actor_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->foreignId('thread_actor_id')->constrained('thread_actors')->cascadeOnDelete();
            $table->string('provider')->default('default');
            $table->string('model')->default('default');
            $table->string('conversation_id', 64)->nullable();
            $table->json('state')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['thread_actor_id', 'provider', 'model'], 'thread_actor_memories_unique');
            $table->index(['thread_id', 'thread_actor_id'], 'thread_actor_memories_thread_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_actor_memories');
    }
};
