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
        Schema::create('channels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->nullable();

            // Protocol/Transport Architecture
            $table->string('driver')->comment('Protocol: generic, mcp, a2a, acp');
            $table->string('transport')->nullable()->comment('Transport: websocket, http, webhook, stdio, webrtc, relay');
            $table->string('server')->nullable()->comment('Server identifier for grouping');

            // Configuration
            $table->string('label')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->string('status')->default('active')->comment('active, paused, disabled');
            $table->string('direction')->default('bidirectional')->comment('inbound, outbound, bidirectional');

            // Connection Details
            $table->string('endpoint_url')->nullable()->comment('Remote endpoint for outbound connections');
            $table->string('handler')->nullable()->comment('Handler class for custom processing');
            $table->string('auth_type')->nullable()->comment('Authentication type: bearer, basic, api_key, oauth2');

            // Security & Configuration
            $table->json('allowed_tools')->nullable()->comment('Allowed tool names for this channel');
            $table->text('credentials')->nullable()->comment('Encrypted credentials');
            $table->json('config')->nullable()->comment('Channel-specific configuration');
            $table->json('meta')->nullable()->comment('Additional metadata');

            // Health & Monitoring
            $table->timestamp('last_connected_at')->nullable()->comment('Last successful connection timestamp');
            $table->timestamp('last_message_at')->nullable()->comment('Last message received/sent timestamp');
            $table->string('health_status')->default('unknown')->comment('healthy, unhealthy, unknown');
            $table->json('metrics')->nullable()->comment('Performance metrics: message_count, error_count, etc.');

            $table->timestamps();
            $table->softDeletes();

            // Optimized indexes for protocol/transport architecture
            $table->index(['transport', 'enabled', 'direction'], 'channels_transport_enabled_direction_idx');
            $table->index(['driver', 'transport', 'status'], 'channels_protocol_transport_status_idx');
            $table->index(['enabled', 'health_status'], 'channels_enabled_health_idx');
            $table->index(['last_message_at'], 'channels_last_message_idx');

            // Legacy compatibility indexes (can remove after full migration)
            $table->index(['driver', 'status'], 'channels_driver_status_index');
            $table->index(['status', 'direction'], 'channels_status_direction_index');
            $table->index(['driver', 'server', 'enabled'], 'channels_driver_server_enabled_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
