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
        Schema::create('post_relations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->morphs('relationable');
            $table->string('role')->default('primary');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['post_id', 'relationable_type', 'relationable_id', 'role'], 'post_relations_unique');
            $table->index(['relationable_type', 'relationable_id', 'role'], 'post_relations_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_relations');
    }
};
