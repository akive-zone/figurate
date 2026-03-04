<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_event_agent_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_event_id');
            $table->foreignId('agent_task_id');
            $table->timestamps();

            $table->unique(['agent_task_id', 'thread_event_id'], 'agent_task_thread_event_unique');
            $table->index(['thread_event_id'], 'agent_task_thread_event_thread_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_event_agent_tasks');
    }
};
