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
        Schema::create('thread_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('thread_id');
            $table->foreignId('thread_actor_id')->nullable();
            $table->foreignId('post_id')->nullable();
            $table->string('event_key');
            $table->string('event_type')->nullable();
            $table->string('layer', 40)->default('execution');
            $table->string('kind', 60)->nullable();
            $table->string('operation', 120)->nullable();
            $table->string('state', 40)->nullable();
            $table->string('severity')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
            $table->index(['thread_id', 'layer', 'kind'], 'thread_events_execution_kind_idx');
            $table->index(['thread_id', 'state'], 'thread_events_execution_state_idx');
            $table->index(['post_id']);
            $table->index(['event_type', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_events');
    }
};
