<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_relations', function (Blueprint $table) {
            $table->dropUnique('space_relations_unique');
            $table->unique(
                ['space_id', 'relationable_type', 'relationable_id', 'type'],
                'space_relations_unique'
            );
            $table->index(['space_id', 'type'], 'space_relations_space_type_index');
            $table->index(['type', 'created_at'], 'space_relations_type_created_index');
        });

        Schema::table('thread_relations', function (Blueprint $table) {
            $table->dropUnique('thread_relations_unique');
            $table->unique(
                ['thread_id', 'relationable_type', 'relationable_id', 'type'],
                'thread_relations_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('space_relations', function (Blueprint $table) {
            $table->dropUnique('space_relations_unique');
            $table->dropIndex('space_relations_space_type_index');
            $table->dropIndex('space_relations_type_created_index');
            $table->unique(['space_id', 'relationable_type', 'relationable_id'], 'space_relations_unique');
        });

        Schema::table('thread_relations', function (Blueprint $table) {
            $table->dropUnique('thread_relations_unique');
            $table->unique(['thread_id', 'relationable_type', 'relationable_id'], 'thread_relations_unique');
        });
    }
};
