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
        Schema::create('thread_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->string('actor_key');
            $table->string('role');
            $table->string('status')->default('active');
            $table->unsignedInteger('priority')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['thread_id', 'actor_key', 'role'], 'thread_actors_unique');
            $table->index(['role', 'status'], 'thread_actors_role_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_actors');
    }
};
