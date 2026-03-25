<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('thread_id');
            $table->foreignId('post_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('status', 40)->default('submitted');
            $table->json('remote')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'status'], 'agent_tasks_thread_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_tasks');
    }
};
