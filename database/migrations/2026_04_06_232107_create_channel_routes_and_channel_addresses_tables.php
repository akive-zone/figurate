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
        Schema::create('channel_routes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('label')->nullable();
            $table->string('status')->default('active');
            $table->string('direction')->default('bidirectional');
            $table->jsonb('config')->nullable();
            $table->jsonb('data')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'status', 'direction'], 'channel_routes_channel_status_direction_index');
        });

        Schema::create('channel_addresses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('channel_route_id')->constrained('channel_routes')->cascadeOnDelete();
            $table->morphs('addressable');
            $table->string('label')->nullable();
            $table->string('provider')->nullable();
            $table->string('target');
            $table->string('target_type')->nullable();
            $table->string('status')->default('active');
            $table->string('direction')->default('bidirectional');
            $table->jsonb('data')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['channel_route_id', 'status', 'direction'], 'channel_addresses_route_status_direction_index');
            $table->index(['channel_route_id', 'provider', 'target'], 'channel_addresses_route_provider_target_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_addresses');
        Schema::dropIfExists('channel_routes');
    }
};
