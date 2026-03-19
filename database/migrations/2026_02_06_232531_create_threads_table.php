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
            $table->uuid('uuid')->unique();
            $table->nullableMorphs('threadable');
            $table->string('title')->nullable();
            $table->string('purpose')->default('main');
            $table->string('phase');
            $table->string('status')->default('open');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['threadable_type', 'threadable_id', 'status'], 'threads_threadable_status_index');
            $table->index(['threadable_type', 'threadable_id', 'phase'], 'threads_threadable_phase_index');
            $table->index(['threadable_type', 'threadable_id', 'purpose'], 'threads_threadable_purpose_index');
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
