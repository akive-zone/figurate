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
        Schema::create('thread_relations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('thread_id');
            $table->morphs('relationable');
            $table->string('type');
            $table->text('purpose')->nullable();
            $table->timestamps();

            $table->unique(['thread_id', 'relationable_type', 'relationable_id'], 'thread_relations_unique');
            $table->index(['thread_id', 'type'], 'thread_relations_thread_type_index');
            $table->index(['type', 'created_at'], 'thread_relations_type_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_relations');
    }
};
