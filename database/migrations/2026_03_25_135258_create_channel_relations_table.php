<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channel_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->nullableMorphs('relationable');
            $table->string('kind')->default('bind');
            $table->string('status')->default('active');
            $table->string('direction')->default('bidirectional');
            $table->jsonb('config')->nullable();
            $table->jsonb('data')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['relationable_type', 'relationable_id', 'kind', 'status'], 'channel_relation_lookup_index');
            $table->index(['channel_id', 'kind', 'status'], 'channel_relation_channel_kind_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_relations');
    }
};
