<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('identity_id')->constrained('identities')->cascadeOnDelete();
            $table->morphs('relatable');
            $table->string('relationship')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('unlinked_at')->nullable();
            $table->timestamps();

            $table->unique(['identity_id', 'relatable_type', 'relatable_id'], 'identity_relations_unique');
            $table->index(['relatable_type', 'relatable_id', 'unlinked_at'], 'identity_relations_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_relations');
    }
};
