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
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->morphs('threadable');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('phase');
            $table->string('agent_key');
            $table->string('ai_conversation_id', 36)->nullable()->unique();
            $table->string('status')->default('open');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['threadable_type', 'threadable_id', 'status'], 'threads_threadable_status_index');
            $table->index(['threadable_type', 'threadable_id', 'phase'], 'threads_threadable_phase_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
