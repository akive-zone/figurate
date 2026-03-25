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
            $table->string('driver');
            $table->string('server')->nullable();
            $table->string('label')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->string('transport')->nullable();
            $table->string('status')->default('active');
            $table->string('direction')->default('bidirectional');
            $table->string('endpoint_url')->nullable();
            $table->string('handler')->nullable();
            $table->json('allowed_tools')->nullable();
            $table->string('auth_type')->nullable();
            $table->text('credentials')->nullable();
            $table->json('config')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

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
