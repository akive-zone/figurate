<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('unlinked_at')->nullable();
            $table->timestamps();

            $table->unique(['source_user_id', 'target_user_id', 'type'], 'user_relations_unique');
            $table->index(['source_user_id', 'type', 'unlinked_at'], 'user_relations_source_lookup_idx');
            $table->index(['target_user_id', 'type', 'unlinked_at'], 'user_relations_target_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_relations');
    }
};
